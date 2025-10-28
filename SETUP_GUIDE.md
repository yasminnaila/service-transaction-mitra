# Setup Guide - Service Transaction Mitra Microservice

Panduan lengkap untuk setup project ini di GitHub pribadi Anda dan integrasi dengan Jenkins.

## Prerequisites

- Git terinstall di komputer Anda
- Akun GitHub
- Jenkins server (sudah terinstall dan berjalan)
- Docker terinstall (untuk build image)
- Akses ke Jenkins dengan Docker plugin

---

## Step 1: Inisialisasi Git Repository

### 1.1 Hapus folder service-transaction-mitra-microservice-main yang duplikat (jika ada)
```powershell
Remove-Item -Recurse -Force "service-transaction-mitra-microservice-main"
```

### 1.2 Inisialisasi Git Repository Baru
```powershell
# Pastikan Anda berada di direktori project
cd c:\Users\L\Downloads\service-transaction-mitra-microservice-main

# Inisialisasi git repository
git init

# Cek status
git status
```

### 1.3 Tambahkan semua file ke staging
```powershell
git add .
```

### 1.4 Buat commit pertama
```powershell
git commit -m "Initial commit: Laravel Transaction Mitra Microservice"
```

---

## Step 2: Push ke GitHub

### 2.1 Buat Repository Baru di GitHub
1. Login ke GitHub.com
2. Klik tombol "+" di pojok kanan atas, pilih "New repository"
3. Beri nama repository, misalnya: `service-transaction-mitra`
4. Pilih **Private** atau **Public** sesuai kebutuhan
5. **JANGAN** centang "Initialize this repository with a README"
6. Klik "Create repository"

### 2.2 Tambahkan Remote Repository
```powershell
# Ganti <USERNAME> dengan username GitHub Anda
# Ganti <REPO-NAME> dengan nama repository yang Anda buat
git remote add origin https://github.com/<USERNAME>/<REPO-NAME>.git

# Contoh:
# git remote add origin https://github.com/johndoe/service-transaction-mitra.git
```

### 2.3 Cek Remote
```powershell
git remote -v
```

### 2.4 Push ke GitHub
```powershell
# Push ke branch main
git branch -M main
git push -u origin main
```

**Note:** Anda mungkin diminta login GitHub. Gunakan Personal Access Token (PAT) sebagai password jika diminta.

### 2.5 Cara Membuat GitHub Personal Access Token (PAT)
1. Buka GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Klik "Generate new token (classic)"
3. Beri nama token, misalnya: "Jenkins-CI"
4. Pilih scopes: minimal **repo** (full control of private repositories)
5. Klik "Generate token"
6. **SIMPAN token ini**, Anda tidak bisa melihatnya lagi setelah halaman ditutup

---

## Step 3: Setup Jenkins

### 3.1 Install Plugin yang Diperlukan di Jenkins
1. Buka Jenkins Dashboard
2. Pergi ke **Manage Jenkins** → **Manage Plugins**
3. Install plugin berikut jika belum ada:
   - Git Plugin
   - Docker Plugin
   - Docker Pipeline
   - GitHub Integration Plugin

### 3.2 Configure Docker di Jenkins
1. Pergi ke **Manage Jenkins** → **Global Tool Configuration**
2. Scroll ke bagian **Docker**
3. Klik "Add Docker"
4. Beri nama, misalnya "docker"
5. Centang "Install automatically" atau masukkan path Docker jika sudah terinstall

### 3.3 Tambahkan GitHub Credentials di Jenkins
1. Pergi ke **Manage Jenkins** → **Manage Credentials**
2. Pilih domain **(global)**
3. Klik "Add Credentials"
4. Pilih jenis: **Username with password**
5. Username: GitHub username Anda
6. Password: Personal Access Token yang sudah dibuat
7. ID: `github-credentials` (atau nama lain yang mudah diingat)
8. Description: "GitHub PAT for repository access"
9. Klik "Create"

### 3.4 Tambahkan Docker Hub Credentials (Opsional - jika ingin push image ke Docker Hub)
1. Pergi ke **Manage Jenkins** → **Manage Credentials**
2. Klik "Add Credentials"
3. Pilih jenis: **Username with password**
4. Username: Docker Hub username Anda
5. Password: Docker Hub password/token
6. ID: `dockerhub-credentials`
7. Klik "Create"

---

## Step 4: Buat Jenkins Pipeline Job

### 4.1 Buat New Item
1. Dari Jenkins Dashboard, klik "New Item"
2. Masukkan nama job, misalnya: `service-transaction-mitra-pipeline`
3. Pilih **Pipeline**
4. Klik "OK"

### 4.2 Configure Pipeline
1. Di bagian **General**:
   - (Opsional) Centang "GitHub project" dan masukkan URL repository GitHub Anda

2. Di bagian **Build Triggers**:
   - Centang "GitHub hook trigger for GITScm polling" (untuk auto-trigger dari GitHub webhook)
   - ATAU centang "Poll SCM" dan masukkan schedule, misalnya: `H/5 * * * *` (setiap 5 menit)

3. Di bagian **Pipeline**:
   - Definition: pilih **Pipeline script from SCM**
   - SCM: pilih **Git**
   - Repository URL: masukkan URL repository GitHub Anda
     ```
     https://github.com/<USERNAME>/<REPO-NAME>.git
     ```
   - Credentials: pilih credentials yang sudah dibuat (`github-credentials`)
   - Branch Specifier: `*/main` (atau `*/master` sesuai branch default Anda)
   - Script Path: `Jenkinsfile`

4. Klik "Save"

---

## Step 5: Setup GitHub Webhook (Opsional - untuk Auto Trigger)

### 5.1 Configure Webhook di GitHub
1. Buka repository GitHub Anda
2. Pergi ke **Settings** → **Webhooks** → **Add webhook**
3. Payload URL: `http://<JENKINS-URL>/github-webhook/`
   ```
   Contoh: http://jenkins.yourcompany.com/github-webhook/
   ```
4. Content type: `application/json`
5. Secret: (kosongkan atau buat secret jika ingin lebih secure)
6. Pilih trigger: **Just the push event**
7. Centang "Active"
8. Klik "Add webhook"

**Note:** Jenkins server Anda harus bisa diakses dari internet agar GitHub bisa mengirim webhook.

---

## Step 6: Kustomisasi Jenkinsfile (Sesuai Kebutuhan)

### 6.1 Edit Jenkinsfile untuk Push ke Docker Registry
Jika Anda ingin push image ke Docker Hub atau registry lain, edit `Jenkinsfile`:

```groovy
environment {
    DOCKER_IMAGE = 'service-transaction-mitra'
    DOCKER_TAG = "${env.BUILD_NUMBER}"
    DOCKER_REGISTRY = 'docker.io/yourusername' // Ganti dengan username Docker Hub Anda
}
```

### 6.2 Enable Stage Deploy
Jika Anda ingin auto-deploy setelah build sukses, uncomment bagian deploy di Jenkinsfile:

```groovy
stage('Deploy') {
    when {
        branch 'main'
    }
    steps {
        script {
            echo 'Deploying application...'
            sh 'docker-compose down'
            sh 'docker-compose up -d'
        }
    }
}
```

---

## Step 7: Menjalankan Pipeline Pertama Kali

### 7.1 Manual Trigger
1. Buka Jenkins job yang sudah dibuat
2. Klik "Build Now"
3. Lihat progress di "Build History"
4. Klik nomor build untuk melihat console output

### 7.2 Cek Build Status
- **Blue/Green**: Build sukses ✅
- **Red**: Build gagal ❌
- **Yellow**: Build unstable ⚠️

---

## Step 8: Testing dan Verification

### 8.1 Cek Docker Image
Setelah build sukses, cek apakah image berhasil dibuat:
```powershell
docker images | Select-String "service-transaction-mitra"
```

### 8.2 Test Run Container
```powershell
docker run --rm -p 9000:9000 service-transaction-mitra:latest
```

### 8.3 Cek dengan Docker Compose
```powershell
docker-compose up -d
docker-compose ps
docker-compose logs -f
```

---

## Troubleshooting

### Issue: Git push denied
**Solution:** Pastikan Anda menggunakan Personal Access Token, bukan password GitHub.

### Issue: Jenkins tidak bisa clone repository
**Solution:** 
- Pastikan credentials sudah benar
- Cek apakah Jenkins server bisa akses GitHub (network/firewall)

### Issue: Docker build failed di Jenkins
**Solution:**
- Pastikan Docker sudah terinstall di Jenkins server
- Pastikan Jenkins user memiliki permission untuk run Docker
- Di Linux/Mac: tambahkan Jenkins user ke docker group
  ```bash
  sudo usermod -aG docker jenkins
  sudo systemctl restart jenkins
  ```

### Issue: GitHub webhook tidak trigger Jenkins
**Solution:**
- Pastikan Jenkins server accessible dari internet
- Cek webhook delivery di GitHub Settings → Webhooks
- Pastikan URL webhook benar dan diakhiri dengan `/github-webhook/`

---

## Environment Variables yang Perlu Diatur

Jangan lupa untuk setup environment variables di Jenkins untuk production:

1. Pergi ke job configuration
2. Di bagian **Pipeline**, tambahkan **Environment variables** atau gunakan **Credentials**
3. Variabel yang biasanya diperlukan:
   - `DB_HOST`
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `REDIS_HOST`
   - `RABBITMQ_HOST`
   - dll (sesuai file `.env`)

---

## Additional Resources

- [Jenkins Pipeline Documentation](https://www.jenkins.io/doc/book/pipeline/)
- [Docker Documentation](https://docs.docker.com/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [GitHub Actions vs Jenkins](https://github.com/features/actions)

---

## Maintenance

### Update Code dan Push
```powershell
git add .
git commit -m "Your commit message"
git push origin main
```

### Hapus Remote (jika ingin ganti repository)
```powershell
git remote remove origin
git remote add origin <NEW-REPO-URL>
```

### Lihat Log Commit
```powershell
git log --oneline
```

---

## Contact & Support

Jika ada pertanyaan atau issue, silakan buat issue di repository GitHub atau hubungi tim development.

---

**Happy Coding! 🚀**
