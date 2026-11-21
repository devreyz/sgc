// Use the PWA icon as the launcher artwork for every Android density.
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const green = '#173c2e', lime = '#d9f172', graphite = '#111418';
const markPath = path.join(root, 'public/assets/sgc-mark-light.png');
const pwaIconPath = path.join(root, 'public/assets/icons/campo-light-512.png');

function pwaIcon(size) {
    return sharp(pwaIconPath).resize(size, size);
}

async function legacyIcon(size, round) {
    const radius = round ? size / 2 : size * .23;
    const mask = Buffer.from(`<svg width="${size}" height="${size}"><rect width="${size}" height="${size}" rx="${radius}" fill="white"/></svg>`);
    return pwaIcon(size).ensureAlpha().composite([{input: mask, blend: 'dest-in'}]).png().toBuffer();
}

function launcherLayer(size, background) {
    return sharp({create: {width: size, height: size, channels: 4, background}});
}

async function adaptiveForeground(size, backgroundPixel) {
    // Android renders the central 72 dp of its 108 dp adaptive icon layers.
    const visibleSize = Math.round(size * 72 / 108);
    const {data, info} = await pwaIcon(visibleSize).ensureAlpha().raw().toBuffer({resolveWithObject: true});
    const [red, green, blue] = backgroundPixel;
    for (let offset = 0; offset < data.length; offset += info.channels) {
        if (data[offset] === red && data[offset + 1] === green && data[offset + 2] === blue) {
            data[offset + 3] = 0;
        }
    }
    return launcherLayer(size, '#00000000')
        .composite([{input: data, raw: {width: info.width, height: info.height, channels: info.channels}, gravity: 'centre'}])
        .png().toBuffer();
}
async function mark(size, color) {
    const alpha = await sharp(markPath).trim().resize(size, size, {fit:'inside',kernel:'lanczos3'}).ensureAlpha().extractChannel('alpha').toBuffer();
    const meta = await sharp(alpha).metadata();
    return sharp({create:{width:meta.width,height:meta.height,channels:3,background:color}}).joinChannel(alpha).png().toBuffer();
}
async function canvas(size, background, glyph, ratio = .62, round = false) {
    const image = await mark(Math.round(size * ratio), glyph);
    let result = sharp({create:{width:size,height:size,channels:4,background}}).composite([{input:image,gravity:'centre'}]);
    if (round) {
        const png = await result.png().toBuffer();
        result = sharp(png).composite([{input:Buffer.from(`<svg width="${size}" height="${size}"><rect width="${size}" height="${size}" rx="${size*.23}" fill="white"/></svg>`),blend:'dest-in'}]);
    }
    return result.png().toBuffer();
}
(async () => {
    const resources = path.join(root,'android/app/src/main/res');
    const backgroundPixel = await sharp(pwaIconPath).extract({left: 0, top: 0, width: 1, height: 1}).removeAlpha().raw().toBuffer();
    const launcherBackground = '#'+backgroundPixel.toString('hex');
    fs.writeFileSync(path.join(resources, 'values/ic_launcher_background.xml'),
        `<?xml version="1.0" encoding="utf-8"?>\n<resources>\n    <color name="ic_launcher_background">${launcherBackground}</color>\n</resources>\n`);
    fs.writeFileSync(path.join(root,'assets/icon-only.png'),await legacyIcon(1024,false));
    await launcherLayer(1024,launcherBackground).png().toFile(path.join(root,'assets/icon-background.png'));
    fs.writeFileSync(path.join(root,'assets/icon-foreground.png'),await adaptiveForeground(1024,backgroundPixel));
    for (const [density, size, adaptive] of [['ldpi',36,81],['mdpi',48,108],['hdpi',72,162],['xhdpi',96,216],['xxhdpi',144,324],['xxxhdpi',192,432]]) {
        const folder=path.join(resources,'mipmap-'+density);
        fs.mkdirSync(folder,{recursive:true});
        fs.writeFileSync(path.join(folder,'ic_launcher.png'),await legacyIcon(size,false));
        fs.writeFileSync(path.join(folder,'ic_launcher_round.png'),await legacyIcon(size,true));
        await launcherLayer(adaptive,launcherBackground).png().toFile(path.join(folder,'ic_launcher_background.png'));
        fs.writeFileSync(path.join(folder,'ic_launcher_foreground.png'),await adaptiveForeground(adaptive,backgroundPixel));
    }
    if (process.argv.includes('--launcher-only')) return;
    for (const folder of fs.readdirSync(resources).filter(name=>name.startsWith('drawable'))) {
        const splash=path.join(resources,folder,'splash.png');
        if (!fs.existsSync(splash)) continue;
        const {width,height}=await sharp(splash).metadata();
        const glyph=await mark(Math.round(Math.min(width,height)*.24),lime);
        await sharp({create:{width,height,channels:4,background:folder.includes('night')?graphite:green}}).composite([{input:glyph,gravity:'centre'}]).png().toFile(splash);
    }
    for(const [name,background] of [['splash.png',green],['splash-dark.png',graphite]]) {
        const target=path.join(root,'assets',name);const {width,height}=await sharp(target).metadata();
        await sharp({create:{width,height,channels:4,background}}).composite([{input:await mark(Math.round(Math.min(width,height)*.24),lime),gravity:'centre'}]).png().toFile(target);
    }
    fs.writeFileSync(path.join(root,'public/assets/sgc-brand-dark.png'),await canvas(512,lime,green,.72,true));
    fs.writeFileSync(path.join(root,'public/assets/sgc-mark-dark.png'),await canvas(256,'#00000000',lime,.92));
    for (const size of [16,32]) fs.writeFileSync(path.join(root,`public/assets/favicon-dark-${size}.png`),await canvas(size,lime,green,.72));
    await sharp(path.join(root,'assets/splash-dark.png')).webp({quality:95}).toFile(path.join(root,'public/assets/splash-dark.webp'));
    for (const size of [192,512]) fs.writeFileSync(path.join(root,`public/assets/icons/campo-dark-${size}.png`),await canvas(size,lime,green,.62));
    console.log('Android launcher densities, splash screens and dark brand regenerated.');
})().catch(error=>{console.error(error);process.exit(1)});
