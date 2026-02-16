@extends('layouts.bento')

@section('title', 'Meu Perfil')
@section('page-title', 'Meu Perfil')
@section('user-role', 'Configurações da Conta')

@section('content')
<div class="bento-grid">
    <!-- Profile Photo Card -->
    <div class="bento-card col-span-12 lg:col-span-4">
        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.5rem;">
            <div style="position: relative;">
                @if($user->avatar)
                    <img id="avatar-preview" src="{{ Storage::url($user->avatar) }}" alt="{{ $user->name }}" 
                         style="width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid var(--color-primary); box-shadow: var(--shadow-lg);">
                @else
                    <div id="avatar-preview" style="width: 140px; height: 140px; border-radius: 50%; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 700; color: white; border: 4px solid var(--color-primary); box-shadow: var(--shadow-lg);">
                        {{ strtoupper(mb_substr($user->name, 0, 2)) }}
                    </div>
                @endif
                <label for="avatar-upload" style="position: absolute; bottom: 5px; right: 5px; width: 40px; height: 40px; border-radius: 50%; background: var(--color-primary); color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: var(--shadow-md); transition: all 0.2s;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                </label>
            </div>
            
            <div>
                <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--color-text); margin-bottom: 0.25rem;">{{ $user->name }}</h2>
                <p style="font-size: 0.95rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">{{ $user->email }}</p>
                
                @if($tenant)
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.85rem; border-radius: 999px; background: rgba(16, 185, 129, 0.1); color: var(--color-primary); font-size: 0.85rem; font-weight: 600;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        </svg>
                        {{ $tenant->name }}
                    </div>
                @endif
            </div>

            @if($user->avatar)
                <form action="{{ route('profile.remove-avatar', ['tenant' => $tenant->slug ?? '']) }}" method="POST" style="width: 100%;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline" style="width: 100%; font-size: 0.875rem;" onclick="return confirm('Deseja remover sua foto de perfil?')">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                        Remover Foto
                    </button>
                </form>
            @endif
        </div>
    </div>

    <!-- Profile Information Form -->
    <div class="bento-card col-span-12 lg:col-span-8">
        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--color-text);">Informações Pessoais</h3>
        
        <form id="profile-form" action="{{ route('profile.update', ['tenant' => $tenant->slug ?? '']) }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- Hidden file inputs -->
            <input type="file" id="avatar-upload" name="avatar_raw" accept="image/*" style="display: none;">
            <input type="hidden" id="avatar-compressed" name="avatar">
            
            <div class="form-group">
                <label class="form-label" for="name">Nome Completo</label>
                <input type="text" id="name" name="name" class="form-input" value="{{ old('name', $user->name) }}" required>
                @error('name')
                    <p style="color: var(--color-danger); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">E-mail</label>
                @if($isAdmin)
                    <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required>
                @else
                    <input type="email" id="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" readonly style="background: var(--color-bg); cursor: not-allowed; opacity: 0.7;">
                    <p style="color: var(--color-text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: inline-block; vertical-align: middle; margin-right: 0.25rem;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Apenas administradores podem alterar o e-mail
                    </p>
                @endif
                @error('email')
                    <p style="color: var(--color-danger); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="padding: 1.25rem; background: var(--color-bg); border-radius: var(--radius-lg); margin-bottom: 1.5rem;">
                <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--color-text);">Alterar Senha</h4>
                <p style="font-size: 0.875rem; color: var(--color-text-muted); margin-bottom: 1rem;">Deixe em branco se não deseja alterar a senha</p>
                
                <div class="form-group">
                    <label class="form-label" for="current_password">Senha Atual</label>
                    <input type="password" id="current_password" name="current_password" class="form-input">
                    @error('current_password')
                        <p style="color: var(--color-danger); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Nova Senha</label>
                    <input type="password" id="password" name="password" class="form-input">
                    @error('password')
                        <p style="color: var(--color-danger); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="password_confirmation">Confirmar Nova Senha</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input">
                </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <a href="{{ url('/') }}" class="btn btn-outline">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const avatarUpload = document.getElementById('avatar-upload');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarCompressed = document.getElementById('avatar-compressed');
    const profileForm = document.getElementById('profile-form');

    avatarUpload.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione uma imagem válida.');
            return;
        }

        // Show loading state
        const originalHTML = avatarPreview.innerHTML || avatarPreview.outerHTML;
        if (avatarPreview.tagName === 'IMG') {
            avatarPreview.style.opacity = '0.5';
        } else {
            avatarPreview.style.opacity = '0.5';
        }

        try {
            // Compress and convert to WebP
            const compressedBlob = await compressImageToWebP(file);
            
            // Convert blob to base64
            const reader = new FileReader();
            reader.onloadend = function() {
                // Update preview
                if (avatarPreview.tagName === 'IMG') {
                    avatarPreview.src = reader.result;
                    avatarPreview.style.opacity = '1';
                } else {
                    const newImg = document.createElement('img');
                    newImg.id = 'avatar-preview';
                    newImg.src = reader.result;
                    newImg.alt = '{{ $user->name }}';
                    newImg.style.cssText = 'width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 4px solid var(--color-primary); box-shadow: var(--shadow-lg);';
                    avatarPreview.parentNode.replaceChild(newImg, avatarPreview);
                }
                
                // Store base64 in hidden input
                avatarCompressed.value = reader.result;
                
                // Auto-submit form
                profileForm.submit();
            };
            reader.readAsDataURL(compressedBlob);
        } catch (error) {
            console.error('Erro ao processar imagem:', error);
            alert('Erro ao processar imagem. Por favor, tente novamente.');
            if (avatarPreview.tagName === 'IMG') {
                avatarPreview.style.opacity = '1';
            } else {
                avatarPreview.style.opacity = '1';
            }
        }
    });

    async function compressImageToWebP(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                const img = new Image();
                
                img.onload = function() {
                    // Create canvas
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    
                    // Calculate new dimensions (max 800x800)
                    let width = img.width;
                    let height = img.height;
                    const maxSize = 800;
                    
                    if (width > height) {
                        if (width > maxSize) {
                            height = (height * maxSize) / width;
                            width = maxSize;
                        }
                    } else {
                        if (height > maxSize) {
                            width = (width * maxSize) / height;
                            height = maxSize;
                        }
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    // Draw image on canvas
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Convert to WebP with 0.85 quality
                    canvas.toBlob(
                        (blob) => {
                            if (blob) {
                                resolve(blob);
                            } else {
                                reject(new Error('Falha ao converter imagem'));
                            }
                        },
                        'image/webp',
                        0.85
                    );
                };
                
                img.onerror = function() {
                    reject(new Error('Falha ao carregar imagem'));
                };
                
                img.src = e.target.result;
            };
            
            reader.onerror = function() {
                reject(new Error('Falha ao ler arquivo'));
            };
            
            reader.readAsDataURL(file);
        });
    }
});
</script>
@endsection
