@extends('layouts.app')

@section('title', 'Cliente')

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between bg-galaxy-transparent">
                        <h4 class="mb-sm-0">Cliente</h4>

                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">Clientes</a></li>
                                <li class="breadcrumb-item active">Cliente</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header align-items-center d-flex">
                            <h4 class="card-title mb-0 flex-grow-1">
                                <i class="ri-user-settings-line align-middle me-1"></i>
                                Informações do Cliente
                            </h4>
                            <div class="flex-shrink-0">
                                <a href="{{ route('clients.index') }}" class="btn btn-secondary btn-sm">
                                    <i class="ri-arrow-left-line align-middle me-1"></i>
                                    Voltar
                                </a>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row gy-4">
                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="name" class="form-label">
                                            Nome Completo <span class="text-danger">*</span>
                                        </label>
                                        <div class="form-icon">
                                            <input type="text"
                                                class="form-control form-control-icon @error('name') is-invalid @enderror"
                                                id="name" name="name" value="{{ old('name', $client->name) }}" disabled>
                                            <i class="ri-user-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="email" class="form-label">
                                            E-mail
                                        </label>
                                        <div class="form-icon">
                                            <input type="email"
                                                class="form-control form-control-icon @error('email') is-invalid @enderror"
                                                id="email" name="email" value="{{ old('email', $client->email) }}" disabled>
                                            <i class="ri-mail-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xxl-6 col-md-6">
                                    <div>
                                        <label for="phone" class="form-label">
                                            Telefone <i class="ri-whatsapp-line text-success"></i>
                                        </label>
                                        <div class="form-icon">
                                            <input type="text"
                                                class="form-control form-control-icon @error('phone') is-invalid @enderror"
                                                id="phone" name="phone" value="{{ old('phone', $client->phone) }}" disabled>
                                            <i class="ri-phone-line"></i>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="ri-information-line fs-16 align-middle"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <strong>Informações do Registro:</strong>
                                                <ul class="mb-0 mt-2">
                                                    <li>Cadastrado em: {{ $client->created_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    <li>Última atualização:
                                                        {{ $client->updated_at->format('d/m/Y H:i') }}
                                                    </li>
                                                    <li>ID: #{{ $client->id }}</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection