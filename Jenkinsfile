pipeline {
    agent any

    environment {
        DOCKER_IMAGE = 'service-transaction-mitra'
        DOCKER_TAG = "${env.BUILD_NUMBER}"
        DOCKER_REGISTRY = '' // Kosongkan jika tidak pakai registry
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
                    docker.build("${DOCKER_IMAGE}:${DOCKER_TAG}")
                    docker.build("${DOCKER_IMAGE}:latest")
                }
            }
        }

        stage('Run Tests') {
            steps {
                script {
                    echo 'Running tests...'
                    // Jalankan basic test - cek apakah container bisa run
                    bat "docker run --rm ${DOCKER_IMAGE}:${DOCKER_TAG} php --version"
                }
            }
        }

        stage('Tag Image') {
            when {
                branch 'main'
            }
            steps {
                script {
                    echo 'Tagging Docker image...'
                    // Tag image dengan build number dan latest
                    bat "docker tag ${DOCKER_IMAGE}:${DOCKER_TAG} ${DOCKER_IMAGE}:build-${BUILD_NUMBER}"
                    echo "Image tagged: ${DOCKER_IMAGE}:build-${BUILD_NUMBER}"
                }
            }
        }

        stage('Deploy to Local') {
            when {
                branch 'main'
            }
            steps {
                script {
                    echo 'Deploying application locally...'
                    // Stop existing containers
                    bat 'docker-compose down || exit 0'
                    // Start new containers
                    bat 'docker-compose up -d'
                    // Show running containers
                    bat 'docker-compose ps'
                }
            }
        }

        stage('Health Check') {
            when {
                branch 'main'
            }
            steps {
                script {
                    echo 'Checking application health...'
                    sleep(time: 10, unit: 'SECONDS')
                    bat 'docker-compose logs --tail=50 service-transaction-mitra'
                }
            }
        }
    }

    post {
        always {
            echo 'Cleaning up...'
            // Don't clean workspace to keep docker-compose running
        }
        success {
            echo 'Pipeline completed successfully!'
            echo "Docker image built: ${DOCKER_IMAGE}:${DOCKER_TAG}"
            echo 'Application deployed and running'
        }
        failure {
            echo 'Pipeline failed!'
            bat 'docker-compose logs || exit 0'
        }
    }
}
