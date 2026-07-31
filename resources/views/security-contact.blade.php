@extends('layouts.app')

@section('title', 'Security Contact - PUPTracker')

@push('styles')
<style>
    .security-contact-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 20px;
    }
    
    .security-contact-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        max-width: 600px;
        width: 100%;
        padding: 40px;
    }
    
    .security-contact-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .security-contact-header h1 {
        color: #667eea;
        font-weight: 700;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .security-contact-header p {
        color: #666;
        font-size: 14px;
    }
    
    .contact-info {
        background: #f8f9fa;
        border-left: 4px solid #667eea;
        padding: 15px;
        margin-bottom: 30px;
        border-radius: 6px;
    }
    
    .contact-info p {
        margin: 8px 0;
        color: #333;
        font-size: 14px;
    }
    
    .contact-info strong {
        color: #667eea;
    }
    
    .form-group label {
        color: #333;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-control {
        border-radius: 6px;
        border: 1px solid #e0e0e0;
        padding: 10px 15px;
    }
    
    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: 600;
        padding: 12px 30px;
        border-radius: 6px;
        width: 100%;
        transition: all 0.3s ease;
    }
    
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        color: white;
    }
    
    .back-link {
        text-align: center;
        margin-top: 20px;
    }
    
    .back-link a {
        color: #667eea;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
    }
    
    .back-link a:hover {
        text-decoration: underline;
    }
</style>
@endpush

@section('content')

<div class="security-contact-container">
    <div class="security-contact-card">
        
        <div class="security-contact-header">
            <h1>
                <i class="bi bi-exclamation-triangle"></i>
                Security Incident Report
            </h1>
            <p>Report security vulnerabilities, data breaches, or incidents</p>
        </div>

        <div class="contact-info">
            <p>
                <strong>📧 Email:</strong> 
                <a href="mailto:security@pup.edu.ph">security@pup.edu.ph</a>
            </p>
            <p>
                <strong>🔒 Response Time:</strong> 
                Within 24 hours
            </p>
            <p>
                <strong>📋 What to report:</strong>
                Security vulnerabilities, data breaches, unauthorized access, or suspicious activities
            </p>
            <p>
                <strong>⚠️ Note:</strong> 
                Please provide detailed information about the security issue discovered
            </p>
        </div>

        <form action="{{ route('security.report.submit') }}" method="POST" id="securityReportForm">
            @csrf

            <div class="form-group mb-3">
                <label for="name">Your Name <span style="color: #dc3545;">*</span></label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    placeholder="Full Name"
                    required
                    value="{{ old('name') }}"
                >
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="email">Email Address <span style="color: #dc3545;">*</span></label>
                <input 
                    type="email" 
                    class="form-control @error('email') is-invalid @enderror" 
                    id="email" 
                    name="email" 
                    placeholder="your.email@pup.edu.ph"
                    required
                    value="{{ old('email') }}"
                >
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="category">Incident Category <span style="color: #dc3545;">*</span></label>
                <select 
                    class="form-control @error('category') is-invalid @enderror" 
                    id="category" 
                    name="category" 
                    required
                >
                    <option value="">-- Select Category --</option>
                    <option value="vulnerability" {{ old('category') === 'vulnerability' ? 'selected' : '' }}>
                        Security Vulnerability
                    </option>
                    <option value="data_breach" {{ old('category') === 'data_breach' ? 'selected' : '' }}>
                        Data Breach
                    </option>
                    <option value="unauthorized_access" {{ old('category') === 'unauthorized_access' ? 'selected' : '' }}>
                        Unauthorized Access
                    </option>
                    <option value="suspicious_activity" {{ old('category') === 'suspicious_activity' ? 'selected' : '' }}>
                        Suspicious Activity
                    </option>
                    <option value="other" {{ old('category') === 'other' ? 'selected' : '' }}>
                        Other
                    </option>
                </select>
                @error('category')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="description">Incident Description <span style="color: #dc3545;">*</span></label>
                <textarea 
                    class="form-control @error('description') is-invalid @enderror" 
                    id="description" 
                    name="description" 
                    rows="5"
                    placeholder="Please provide detailed information about the security incident..."
                    required
                >{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-3">
                <label for="affected_areas">Affected Areas/Systems</label>
                <input 
                    type="text" 
                    class="form-control @error('affected_areas') is-invalid @enderror" 
                    id="affected_areas" 
                    name="affected_areas" 
                    placeholder="e.g., Student Portal, User Database"
                    value="{{ old('affected_areas') }}"
                >
                @error('affected_areas')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group mb-4">
                <div class="form-check">
                    <input 
                        class="form-check-input @error('confidential') is-invalid @enderror" 
                        type="checkbox" 
                        id="confidential" 
                        name="confidential"
                        value="1"
                        {{ old('confidential') ? 'checked' : '' }}
                    >
                    <label class="form-check-label" for="confidential">
                        Keep my report confidential
                    </label>
                    @error('confidential')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="bi bi-send"></i>
                Submit Security Report
            </button>

        </form>

        <div class="back-link">
            <a href="{{ route('home') }}">
                <i class="bi bi-arrow-left"></i>
                Back to Home
            </a>
        </div>

    </div>
</div>

@endsection
