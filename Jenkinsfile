pipeline {
    agent any
    
    environment {
        DOCKER_IMAGE = 'service-transaction-mitra'
        DOCKER_TAG = "${env.BUILD_NUMBER}"
        DOCKER_REGISTRY = '' // Kosongkan jika tidak pakai registry, atau isi dengan 'docker.io/username' atau registry lain
    }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Build Docker Image') {
            steps {
                script {
                    echo 'Building Docker image...'
                    if (env.DOCKER_REGISTRY) {
                        docker.build("${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${DOCKER_TAG}")
                        docker.build("${DOCKER_REGISTRY}/${DOCKER_IMAGE}:latest")
                    } else {
                        docker.build("${DOCKER_IMAGE}:${DOCKER_TAG}")
                        docker.build("${DOCKER_IMAGE}:latest")
                    }
                }
            }
        }
        
        stage('Run Tests') {
            steps {
                script {
                    echo 'Running tests...'
                    // Uncomment jika ingin menjalankan tests
                    // sh 'docker run --rm ${DOCKER_IMAGE}:${DOCKER_TAG} php artisan test'
                }
            }
        }
        
        stage('Push to Registry') {
            when {
                branch 'main' // Hanya push ke registry jika branch main
            }
            steps {
                script {
                    if (env.DOCKER_REGISTRY) {
                        echo 'Pushing to Docker registry...'
                        docker.withRegistry('https://index.docker.io/v1/', 'dockerhub-credentials') {
                            docker.image("${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${DOCKER_TAG}").push()
                            docker.image("${DOCKER_REGISTRY}/${DOCKER_IMAGE}:latest").push()
                        }
                    } else {
                        echo 'Skipping push - no registry configured'
                    }
                }
            }
        }
        
        stage('Deploy') {
            when {
                branch 'main'
            }
            steps {
                script {
                    echo 'Deploying application...'
                    // Uncomment dan sesuaikan dengan environment deployment Anda
                    // sh 'docker-compose down'
                    // sh 'docker-compose up -d'
                }
            }
        }
    }
    
    post {
        always {
            cleanWs()
        }
        success {
            echo 'Pipeline completed successfully!'
        }
        failure {
            echo 'Pipeline failed!'
        }
    }
}
