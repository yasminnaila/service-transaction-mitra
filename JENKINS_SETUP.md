# Jenkins Setup Guide - Service Transaction Mitra

Repository sudah berhasil di-push ke GitHub:
**https://github.com/yasminnaila/service-transaction-mitra.git**

Sekarang ikuti langkah berikut untuk connect dengan Jenkins.

---

## 🔧 Prerequisites

Pastikan Jenkins server Anda sudah memiliki:
- ✅ Docker installed
- ✅ Git installed
- ✅ Plugin yang diperlukan (akan dijelaskan di bawah)

---

## 📦 STEP 1: Install Jenkins Plugins

1. Login ke **Jenkins Dashboard**
2. Pergi ke **Manage Jenkins** → **Manage Plugins** (atau **Plugins** di Jenkins versi baru)
3. Klik tab **Available plugins**
4. Cari dan install plugin berikut:
   - ✅ **Git** (untuk clone repository)
   - ✅ **GitHub** (untuk integrasi dengan GitHub)
   - ✅ **Docker** (untuk build Docker image)
   - ✅ **Docker Pipeline** (untuk Docker commands di pipeline)
   - ✅ **Pipeline** (biasanya sudah ada by default)

5. Centang "**Restart Jenkins when installation is complete and no jobs are running**"
6. Tunggu Jenkins restart

---

## 🔑 STEP 2: Tambahkan GitHub Credentials

### Opsi A: Menggunakan Personal Access Token (RECOMMENDED)

1. **Buat GitHub Personal Access Token** terlebih dahulu:
   - Login ke GitHub
   - Klik profile picture → **Settings**
   - Scroll ke bawah → **Developer settings** → **Personal access tokens** → **Tokens (classic)**
   - Klik **Generate new token (classic)**
   - Beri nama: `Jenkins-CI-service-transaction-mitra`
   - Pilih expiration sesuai kebutuhan (recommend: 90 days atau No expiration)
   - **Select scopes**:
     - ✅ **repo** (full control of private repositories)
     - ✅ **admin:repo_hook** (jika ingin auto-trigger)
   - Klik **Generate token**
   - ⚠️ **COPY dan SIMPAN token** (Anda tidak bisa melihatnya lagi!)

2. **Tambahkan Credentials di Jenkins**:
   - Di Jenkins, pergi ke **Manage Jenkins** → **Manage Credentials**
   - Klik **(global)** domain
   - Klik **Add Credentials**
   - Isi form:
     - **Kind**: `Username with password`
     - **Scope**: `Global`
     - **Username**: `yasminnaila` (GitHub username Anda)
     - **Password**: `<paste GitHub Personal Access Token>`
     - **ID**: `github-yasminnaila` (ID ini akan digunakan di job)
     - **Description**: `GitHub PAT for yasminnaila`
   - Klik **Create**

### Opsi B: Menggunakan SSH Key (Alternative)

Jika Anda prefer SSH:
1. Generate SSH key di server Jenkins
2. Tambahkan public key ke GitHub Account Settings → SSH Keys
3. Di Jenkins Credentials, pilih Kind: `SSH Username with private key`

---

## 🔨 STEP 3: Buat Jenkins Pipeline Job

1. Dari **Jenkins Dashboard**, klik **New Item**
2. **Enter an item name**: `service-transaction-mitra-pipeline`
3. Pilih **Pipeline**
4. Klik **OK**

### Configure Pipeline:

#### A. General Section:
- ✅ (Optional) Centang **GitHub project**
  - Project url: `https://github.com/yasminnaila/service-transaction-mitra/`

#### B. Build Triggers:
Pilih salah satu atau kombinasi:

- **Opsi 1: GitHub hook trigger** (Recommended - auto trigger saat ada push)
  - ✅ Centang **GitHub hook trigger for GITScm polling**
  - (Memerlukan webhook setup di GitHub - dijelaskan di STEP 5)

- **Opsi 2: Poll SCM** (Check repository secara berkala)
  - ✅ Centang **Poll SCM**
  - Schedule: `H/5 * * * *` (setiap 5 menit)
  - Atau: `H/15 * * * *` (setiap 15 menit)

- **Opsi 3: Manual trigger only**
  - Tidak centang apa-apa (hanya bisa trigger manual via "Build Now")

#### C. Pipeline Section:
- **Definition**: `Pipeline script from SCM`
- **SCM**: `Git`
- **Repository URL**: `https://github.com/yasminnaila/service-transaction-mitra.git`
- **Credentials**: Pilih `yasminnaila/****** (GitHub PAT for yasminnaila)`
- **Branches to build**:
  - Branch Specifier: `*/main`
- **Script Path**: `Jenkinsfile`

#### D. Save
Klik **Save**

---

## 🚀 STEP 4: Test First Build

1. Di halaman job `service-transaction-mitra-pipeline`, klik **Build Now**
2. Lihat progress di **Build History** (#1, #2, dst)
3. Klik nomor build untuk melihat detail
4. Klik **Console Output** untuk melihat log detail

### Expected Result:
- ✅ Stage: Checkout → SUCCESS (clone repository dari GitHub)
- ✅ Stage: Build Docker Image → SUCCESS (build Docker image)
- ✅ Stage: Run Tests → SUCCESS (skip jika belum enable)
- ⚠️ Stage: Push to Registry → SKIPPED (karena DOCKER_REGISTRY kosong)
- ⚠️ Stage: Deploy → SKIPPED (karena masih di-comment)

---

## 🔔 STEP 5: Setup GitHub Webhook (Optional - untuk Auto Trigger)

⚠️ **Syarat**: Jenkins server Anda harus bisa diakses dari internet (memiliki public IP atau domain)

### A. Di Jenkins:
1. Pastikan Jenkins memiliki URL yang accessible dari internet
   - Contoh: `http://jenkins.yourcompany.com` atau `http://123.456.789.0:8080`
2. Pergi ke **Manage Jenkins** → **Configure System**
3. Cari **GitHub** section
4. Pastikan ada GitHub Server configuration (jika belum, add new)

### B. Di GitHub:
1. Buka repository: https://github.com/yasminnaila/service-transaction-mitra
2. Klik **Settings** (di repository, bukan account settings)
3. Klik **Webhooks** di sidebar kiri
4. Klik **Add webhook**
5. Isi form:
   - **Payload URL**: `http://<JENKINS-URL>/github-webhook/`
     - Contoh: `http://jenkins.yourcompany.com/github-webhook/`
     - ⚠️ Jangan lupa `/github-webhook/` di akhir!
   - **Content type**: `application/json`
   - **Secret**: (kosongkan atau buat secret untuk keamanan)
   - **Which events would you like to trigger this webhook?**:
     - ✅ Select **Just the push event**
   - ✅ Centang **Active**
6. Klik **Add webhook**

### C. Test Webhook:
1. Di GitHub Webhooks, klik webhook yang baru dibuat
2. Scroll ke bawah ke **Recent Deliveries**
3. Klik delivery terakhir untuk melihat response
4. ✅ Response code 200 = sukses
5. ❌ Response code 4xx/5xx = ada masalah

### D. Test dengan Push:
```powershell
# Buat perubahan kecil
echo "# Test webhook" >> README.md
git add README.md
git commit -m "Test webhook trigger"
git push origin main
```

Jenkins seharusnya otomatis trigger build baru!

---

## 🐳 STEP 6: Konfigurasi Docker (Optional - jika ingin push ke Docker Hub)

### A. Buat Docker Hub Account (jika belum punya):
1. Daftar di https://hub.docker.com
2. Buat repository baru (misalnya: `yasminnaila/service-transaction-mitra`)

### B. Tambahkan Docker Hub Credentials di Jenkins:
1. **Manage Jenkins** → **Manage Credentials** → **(global)** → **Add Credentials**
2. Isi:
   - **Kind**: `Username with password`
   - **Username**: Docker Hub username
   - **Password**: Docker Hub password (atau Access Token)
   - **ID**: `dockerhub-credentials`
   - **Description**: `Docker Hub Credentials`
3. Klik **Create**

### C. Update Jenkinsfile:
Edit file `Jenkinsfile` di repository, ubah bagian environment:

```groovy
environment {
    DOCKER_IMAGE = 'service-transaction-mitra'
    DOCKER_TAG = "${env.BUILD_NUMBER}"
    DOCKER_REGISTRY = 'docker.io/yasminnaila' // ← Ubah ini
}
```

Commit dan push perubahan:
```powershell
git add Jenkinsfile
git commit -m "Configure Docker registry for push"
git push origin main
```

---

## 🔍 STEP 7: Monitoring & Troubleshooting

### Cek Build Status:
- **Blue/Green ball** 🟢 = Build SUCCESS
- **Red ball** 🔴 = Build FAILED
- **Yellow ball** 🟡 = Build UNSTABLE
- **Grey ball** ⚪ = Build NOT RUN / ABORTED

### Common Issues:

#### Issue 1: "Failed to connect to repository"
**Solution:**
- Cek credentials sudah benar
- Cek network/firewall tidak block GitHub
- Cek repository URL benar

#### Issue 2: "docker: command not found"
**Solution:**
- Install Docker di Jenkins server
- Pastikan Jenkins user punya akses ke Docker:
  ```bash
  # Di Linux:
  sudo usermod -aG docker jenkins
  sudo systemctl restart jenkins
  ```

#### Issue 3: "Permission denied" saat build Docker
**Solution:**
- Pastikan Jenkins user dalam group docker
- Atau run Jenkins sebagai user yang punya akses Docker

#### Issue 4: Webhook tidak trigger Jenkins
**Solution:**
- Pastikan Jenkins accessible dari internet
- Cek webhook delivery status di GitHub
- Pastikan URL webhook benar (pakai `/github-webhook/`)

---

## 📊 STEP 8: View Build Results

### A. Console Output:
Klik build number → **Console Output** untuk melihat log lengkap

### B. Stage View:
Di halaman job, Anda akan melihat **Stage View** yang menampilkan:
- Checkout (berapa lama)
- Build Docker Image (berapa lama)
- Run Tests (berapa lama)
- dll

### C. Build History:
Lihat semua build yang pernah dijalankan dengan status masing-masing

---

## ⚙️ STEP 9: Customization (Optional)

### A. Enable Unit Tests:
Edit `Jenkinsfile`, uncomment bagian tests:
```groovy
stage('Run Tests') {
    steps {
        script {
            echo 'Running tests...'
            sh 'docker run --rm ${DOCKER_IMAGE}:${DOCKER_TAG} php artisan test'
        }
    }
}
```

### B. Enable Auto Deploy:
Edit `Jenkinsfile`, uncomment bagian deploy:
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

### C. Add Environment Variables:
Di Job Configuration → Pipeline → tambahkan environment variables atau gunakan Credentials

---

## ✅ Checklist Setup

- [ ] Jenkins plugins installed (Git, GitHub, Docker, Docker Pipeline)
- [ ] GitHub Personal Access Token created
- [ ] GitHub credentials added to Jenkins
- [ ] Jenkins pipeline job created
- [ ] First build successful
- [ ] (Optional) GitHub webhook configured
- [ ] (Optional) Docker Hub credentials added
- [ ] (Optional) Jenkinsfile customized

---

## 🎯 Next Steps

Setelah setup selesai:

1. **Develop**: Lakukan development di local
2. **Commit & Push**: 
   ```powershell
   git add .
   git commit -m "Your message"
   git push origin main
   ```
3. **Auto Build**: Jenkins akan otomatis build (jika webhook enabled)
4. **Monitor**: Cek build status di Jenkins dashboard
5. **Deploy**: Setelah build sukses, aplikasi bisa di-deploy

---

## 📝 Useful Git Commands

```powershell
# Cek status
git status

# Lihat history commit
git log --oneline

# Buat branch baru
git checkout -b feature/new-feature

# Push branch baru
git push -u origin feature/new-feature

# Merge ke main
git checkout main
git merge feature/new-feature
git push origin main

# Pull latest changes
git pull origin main
```

---

## 📞 Support

Jika ada masalah atau pertanyaan:
1. Cek Console Output di Jenkins untuk error details
2. Cek GitHub webhook delivery status
3. Cek Jenkins logs di server

---

**Selamat! Setup Jenkins CI/CD Anda sudah siap! 🎉**
