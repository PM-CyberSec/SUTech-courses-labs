@extends('layouts.master')
@section('title', 'Welcome')
@section('content')

<div class="container products-page py-5 px-3">
    {{-- Header Section --}}
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-cyan text-glow-cyan mb-3">Paula Maged Habib</h1>
        <div class="d-flex justify-content-center gap-3">
            <a href="https://www.linkedin.com/in/paula-maged-04a721249/" target="_blank" rel="noopener" class="btn btn-future px-4">
                <i class="bi bi-linkedin me-2"></i>LinkedIn
            </a>
            <a href="https://github.com/PM-CyberSec" target="_blank" rel="noopener" class="btn btn-outline-info px-4">
                <i class="bi bi-github me-2"></i>GitHub
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Professional Summary --}}
        <div class="col-12">
            <div class="card glass-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 text-cyan mb-3 text-uppercase fw-bold" style="letter-spacing: 0.1rem;">Professional Summary</h2>
                    <div class="p-3 rounded-4 glass-effect info-container">
                        <p class="text-light mb-0" style="line-height: 1.6;">
                            High-aptitude Cybersecurity student with a dedicated focus on Blue Team operations and Incident Response. 
                            Actively developing hands-on skills through Blue Team Labs Online (BTLO) and independent project builds. 
                            Gaining practical exposure to SIEM workflows, network traffic analysis, and automated response architectures.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- This Web Application --}}
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-body p-4">
                    <h3 class="h5 text-cyan mb-3 text-uppercase fw-bold" style="letter-spacing: 0.1rem;">This Web Application</h3>
                    <div class="p-3 rounded-4 glass-effect info-container">
                        <p class="text-light-50 small mb-2">A robust product management interface built with <strong>Laravel (Blade)</strong>, featuring:</p>
                        <ul class="text-light small mb-0 ps-3">
                            <li>Secure User Authentication</li>
                            <li>Dynamic Product Listings & Categorization</li>
                            <li>Multi-Tag Support with Searchable Inputs</li>
                            <li>AJAX-powered Inline Editing & Modals</li>
                            <li>Strict Server-side Validation</li>
                            <li>Futuristic "Cosmic" Design System</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- My Journey & What I Learned --}}
        <div class="col-md-6">
            <div class="card glass-card h-100">
                <div class="card-body p-4">
                    <h3 class="h5 text-cyan mb-3 text-uppercase fw-bold" style="letter-spacing: 0.1rem;">My Journey & What I Learned</h3>
                    <div class="p-3 rounded-4 glass-effect info-container">
                        <p class="text-light-50 small mb-2">During the development of this project, I mastered several core engineering concepts:</p>
                        <ul class="text-light small mb-0 ps-3">
                            <li><strong>Database Architecture:</strong> Implementing Migrations and Seeders for scalable data.</li>
                            <li><strong>MVC Pattern:</strong> Deepening understanding of Controllers and RESTful Routing.</li>
                            <li><strong>Dynamic UI:</strong> Orchestrating AJAX CRUD operations for seamless UX.</li>
                            <li><strong>A11y:</strong> Ensuring accessibility through semantic HTML and ARIA standards.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Course Learnings --}}
        <div class="col-12 mt-4">
            <div class="card glass-card">
                <div class="card-body p-4">
                    <h3 class="h5 text-cyan mb-3 text-uppercase fw-bold" style="letter-spacing: 0.1rem;">Course Learnings</h3>
                    <div class="p-3 rounded-4 glass-effect info-container">
                        <div class="row">
                            <div class="col-md-4">
                                <ul class="text-light small mb-0 ps-3">
                                    <li>Full-Stack Web Development (Laravel, PHP, Blade, Bootstrap, MySQL)</li>
                                    <li>Secure Authentication & Authorization Systems</li>
                                    <li>Email Verification & Protected Route Access</li>
                                    <li>RESTful CRUD Operations & MVC Architecture</li>
                                    <li>Database Design, Migrations, Seeders & Eloquent ORM</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="text-light small mb-0 ps-3">
                                    <li>One-to-Many and Many-to-Many Relationships</li>
                                    <li>Product Management System with Categories & Tags</li>
                                    <li>Secure Password Hashing & Input Validation</li>
                                    <li>OWASP Top 10 Web Security Principles</li>
                                    <li>SQL Injection, XSS, CSRF & Access Control Protection</li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <ul class="text-light small mb-0 ps-3">
                                    <li>Cryptography Fundamentals: Encryption, Hashing & Digital Signatures</li>
                                    <li>SSL/TLS & Secure Web Communication Concepts</li>
                                    <li>Responsive UI/UX Design with Bootstrap & Custom CSS</li>
                                    <li>Git & GitHub Version Control Workflow</li>
                                    <li>Secure Coding Practices & Web Application Hardening</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center mt-5">
        <a href="{{ route('products.index') }}" class="btn btn-lg btn-future px-5 py-3 rounded-pill shadow-lg">
            Explore Products
        </a>
    </div>
</div>

@endsection
