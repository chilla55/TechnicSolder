@extends('layouts.master')
@section('title')
    <title>{{ $user->username }} - Technic Solder</title>
@stop
@section('content')
    <div class="page-header">
        <h1>User Management</h1>
    </div>
    <div class="panel panel-default">
        <div class="panel-heading">
            <strong>Edit User:</strong> {{ $user->email }}
            <span style="float: right;">
                <i class="fa fa-bullhorn fa-1"></i>
                <strong>Last Updated By:</strong>
                @if ($userUpdatedBy)
                    {{ $userUpdatedBy->username }}
                @else
                    N/A
                @endif
                - <em>{{ empty($user->updated_by_ip) ? "N/A" : $user->updated_by_ip }}</em>
            </span>
        </div>
        <div class="panel-body">
            @if ($errors->all())
                <div class="alert alert-danger">
                    @foreach ($errors->all() as $error)
                        {{ $error }}<br/>
                    @endforeach
                </div>
            @endif
            @if (Session::has('success'))
                <div class="alert alert-success">
                    {{ Session::get('success') }}
                </div>
            @endif
            <form action="{{ url()->current() }}" method="post" accept-charset="UTF-8">
                @csrf
                <input type="hidden" name="edit-user" value="1">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="text" class="form-control" name="email" id="email" value="{{ $user->email }}">
                        </div>
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text"
                                   class="form-control"
                                   name="username"
                                   id="username"
                                   value="{{ $user->username }}"
                            >
                        </div>
                        <hr>
                        <p>If you would like to change this accounts password you may include new passwords below. This
                            is not required to edit an account</p>
                        <div class="form-group">
                            <label for="password1">Password</label>
                            <input type="password" class="form-control" name="password1" id="password1">
                        </div>
                        <div class="form-group">
                            <label for="password2">Password Again</label>
                            <input type="password" class="form-control" name="password2" id="password2">
                        </div>
                        <input type="submit" class="btn btn-success" value="Save User">
                        <a href="{{ url('/user/list') }}" class="btn btn-primary">Go Back</a>
                    </div>
                    <div class="col-md-6">
                        @if (Auth::user()->permission->solder_full || Auth::user()->permission->solder_users)
                            <h3>Permissions</h3>
                            <p>
                                Please select the level of access this user will be given. The "Solderwide" permission
                                is required to access a specific section. Mod and Modpack user permissions are displayed
                                in there corresponding sections.
                            </p>
                            <div class="form-group">
                                <label>Solderwide</label>
                                <div class="controls">
                                    <label for="solder-full" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="solder-full"
                                               id="solder-full"
                                               @checked($user->permission->solder_full)
                                        >
                                        Full Solder Access (Blanket permission)</label>
                                    <label for="manage-users" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="manage-users"
                                               id="manage-users"
                                               @checked($user->permission->solder_users)
                                        >
                                        Manage Users</label>
                                    <label for="manage-keys" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="manage-keys"
                                               id="manage-keys"
                                               @checked($user->permission->solder_keys)
                                        >
                                        Manage API Keys</label>
                                    <label for="manage-clients" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="manage-clients"
                                               id="manage-clients"
                                               @checked($user->permission->solder_clients)
                                        >
                                        Manage Clients</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Mod Library</label>
                                <div class="controls">
                                    <label for="mod-create" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="mod-create"
                                               id="mod-create"
                                               @checked($user->permission->mods_create)
                                        >
                                        Create Mods</label>
                                    <label for="mod-manage" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="mod-manage"
                                               id="mod-manage"
                                               @checked($user->permission->mods_manage)
                                        >
                                        Manage Mods</label>
                                    <label for="mod-delete" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="mod-delete"
                                               id="mod-delete"
                                               @checked($user->permission->mods_delete)
                                        >
                                        Delete Mods</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label">General Modpack Access</label>
                                <p>General Modpack Access permissions are required before granting access to a specific
                                    modpack. Users without these permission will not be able to perform stated actions
                                    even if the specific modpack is selected.</p>
                                <div class="controls">
                                    <label for="modpack-create" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="modpack-create"
                                               id="modpack-create"
                                               @checked($user->permission->modpacks_create)
                                        >
                                        Create Modpacks</label>
                                    <label for="modpack-manage" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="modpack-manage"
                                               id="modpack-manage"
                                               @checked($user->permission->modpacks_manage)
                                        >
                                        Manage Modpacks</label>
                                    <label for="modpack-delete" class="checkbox-inline">
                                        <input type="checkbox"
                                               name="modpack-delete"
                                               id="modpack-delete"
                                               @checked($user->permission->modpacks_delete)
                                        >
                                        Delete Modpacks</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label">Specific Modpacks</label>
                                <div class="controls">
                                    @forelse ($allModpacks as $modpack)
                                        <label for="{{ $modpack->slug }}" class="checkbox-inline">
                                            <input type="checkbox"
                                                   name="modpack[]"
                                                   id="{{ $modpack->slug }}"
                                                   value="{{ $modpack->id }}"
                                                   @checked(in_array($modpack->id, $user->permission->modpacks))
                                            > {{ $modpack->name }}
                                        </label>
                                    @empty
                                        <p>No modpacks exist.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
