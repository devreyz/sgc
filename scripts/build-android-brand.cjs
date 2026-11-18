// Rebuild launcher resources from the clean PWA mark, preserving its alpha mask.
const fs = require('fs');
const path = require('path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const green = '#173c2e', lime = '#d9f172', graphite = '#111418';
const markPath = path.join(root, 'public/assets/sgc-mark-light.png');
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
    fs.writeFileSync(path.join(root,'assets/icon-only.png'),await canvas(1024,lime,green,.72,true));
    fs.writeFileSync(path.join(root,'assets/icon-background.png'),await canvas(1024,lime,lime));
    fs.writeFileSync(path.join(root,'assets/icon-foreground.png'),await canvas(1024,'#00000000',green));
    for (const [density, size, adaptive] of [['ldpi',36,81],['mdpi',48,108],['hdpi',72,162],['xhdpi',96,216],['xxhdpi',144,324],['xxxhdpi',192,432]]) {
        const folder=path.join(resources,'mipmap-'+density);
        fs.mkdirSync(folder,{recursive:true});
        fs.writeFileSync(path.join(folder,'ic_launcher.png'),await canvas(size,lime,green,.72,true));
        const square=await canvas(size,lime,green,.68);
        fs.writeFileSync(path.join(folder,'ic_launcher_round.png'),await sharp(square).composite([{input:Buffer.from(`<svg width="${size}" height="${size}"><circle cx="${size/2}" cy="${size/2}" r="${size/2}" fill="white"/></svg>`),blend:'dest-in'}]).png().toBuffer());
        fs.writeFileSync(path.join(folder,'ic_launcher_background.png'),await canvas(adaptive,lime,lime));
        const foreground=await canvas(adaptive,'#00000000',green);
        fs.writeFileSync(path.join(folder,'ic_launcher_foreground.png'),foreground);
        const night=path.join(resources,'mipmap-night-'+density);
        fs.mkdirSync(night,{recursive:true});
        fs.writeFileSync(path.join(night,'ic_launcher_foreground.png'),foreground);
    }
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
    console.log('Android launcher densities, night variants, splash screens and dark brand regenerated.');
})().catch(error=>{console.error(error);process.exit(1)});
