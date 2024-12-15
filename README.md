# Laravel REST API with Repository Pattern

## 📋 프로젝트 개요
PHP 8.1과 Laravel 10을 기반으로 개발된 REST API 프로젝트입니다.  
Repository 패턴을 활용하여 코드의 유지보수성을 높였으며, Laravel Scout와 Typesense를 연동해 AI 기반의 검색 기능(유사어 및 근접도 처리)을 구현했습니다.  
인증은 Laravel Sanctum과 Socialite(Google 로그인)를 사용하며, DynamoDB는 푸시 서버 전용으로 연동되어 Lambda 트리거를 통한 이벤트 처리를 지원합니다.  
또한, 큐와 스케줄링 기능을 추가하여 대규모 데이터 처리 및 알림 작업을 비동기로 처리합니다.

---

## ⚙️ 기술 스택
- **언어**: PHP 8.1  
- **프레임워크**: Laravel 10  
- **데이터베이스**: MySQL (주 데이터베이스), AWS DynamoDB (푸시 서버 전용)  
- **검색 엔진**: Typesense + Laravel Scout  
- **인증**: Laravel Sanctum, Socialite (Google 로그인)  
- **운영체제**: Ubuntu + Nginx  

---

## 🛠 주요 기능

### 1️⃣ Repository Pattern
- Repository 패턴과 Service 레이어를 활용하여 데이터베이스 및 외부 서비스의 의존성을 분리.  
- DynamoDB와 Mail 서비스도 Service 레이어로 관리.  

### 2️⃣ AWS DynamoDB 연동
- DynamoDB는 푸시 알림 서버 전용으로 사용.  
- DynamoDB에 값이 추가되면 Lambda 트리거가 발동하여 비즈니스 로직을 처리.  

### 3️⃣ Typesense 통합
- Laravel Scout와 Typesense를 연동해 AI 기반 검색(유사어 및 근접도 처리) 지원.  
- 정교한 검색 결과를 제공하며 대량 데이터 동기화 처리.  

### 4️⃣ 인증 및 검증
- Sanctum을 사용한 토큰 기반 인증과 Socialite로 Google 로그인 지원.  
- Request 클래스에서 데이터 검증을 수행하며, 실패 시 사용자 친화적인 메시지 반환.  

### 5️⃣ 큐와 스케줄링
- Laravel Queue를 사용해 비동기 작업 및 알림 전송 처리.  
- 스케줄링을 통해 정기적인 작업을 자동화.  

---

## 📂 주요 폴더 구조
```plaintext
/project-root
├── /app
│   ├── /Http
│   │   ├── /Controllers  # HTTP 요청 및 응답 처리
│   │   ├── /Requests     # 데이터 검증 규칙 정의
│   │   ├── /Resources    # 반환 데이터 포맷 정의
│   ├── /Services         # 외부 서비스 연동 (DynamoDB, Mail 등)
│   ├── /Repositories     # Repository 패턴 구현
│   ├── /Rules            # 커스텀 검증 규칙 관리
├── /config
├── /routes
│   ├── api.php           # API 경로 정의
├── /resources
│   ├── /views            # Blade 템플릿(이메일, 로그인등 기본 뷰)
└── /README.md            # 프로젝트 문서
