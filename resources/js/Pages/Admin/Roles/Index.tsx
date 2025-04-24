"use client";

import React, { useState, useEffect } from "react";
import { Head, usePage, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

interface Role {
  id: number;
  name: string;
  permissions: { id: number; name: string }[];
}

interface Permission {
  id: number;
  name: string;
}

const RolesIndex = () => {
  const { roles: initialRoles = [], permissions: allPermissions = [] } = usePage().props as {
    roles: Role[];
    permissions: Permission[];
  };

  const [roles, setRoles] = useState<Role[]>(initialRoles);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [modalType, setModalType] = useState<"create" | "edit" | "delete" | "">("");
  const [selectedRole, setSelectedRole] = useState<Role | null>(null);
  const [formState, setFormState] = useState<{ name: string; permissions: number[] }>({
    name: "",
    permissions: [],
  });
  const [errors, setErrors] = useState<{ [key: string]: string }>({});
  const [permissionKey, setPermissionKey] = useState(0);
  const [isLoading, setIsLoading] = useState(false);
  const [successMessage, setSuccessMessage] = useState("");

  // Show success message for 3 seconds and then hide it
  useEffect(() => {
    if (successMessage) {
      const timer = setTimeout(() => {
        setSuccessMessage("");
      }, 3000);
      return () => clearTimeout(timer);
    }
  }, [successMessage]);

  useEffect(() => {
    if (modalType === "edit" && selectedRole) {
      setFormState({
        name: selectedRole.name || "",
        permissions: selectedRole.permissions?.map((p) => p.id) || [],
      });
    } else if (modalType === "create") {
      setFormState({ name: "", permissions: [] });
    }
    setErrors({});
  }, [modalType, selectedRole]);

  const handleOpenModal = (type: "create" | "edit" | "delete", role: Role | null = null) => {
    setModalType(type);
    setSelectedRole(role);
    setIsModalOpen(true);
    setErrors({});
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setModalType("");
    setSelectedRole(null);
    setFormState({ name: "", permissions: [] });
    setErrors({});
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);

    const validationErrors: { [key: string]: string } = {};
    if (!formState.name.trim()) {
      validationErrors.name = "The role name is required";
    }

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      setIsLoading(false);
      return;
    }

    const formData = {
      name: formState.name,
      permissions: formState.permissions,
    };

    if (modalType === "create") {
      router.post("/roles", formData, {
        onSuccess: (response: any) => {
          setRoles((prevRoles) => [...prevRoles, response.data]);
          handleCloseModal();
          setSuccessMessage("Role created successfully!");
          setIsLoading(false);
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    } else if (modalType === "edit" && selectedRole) {
      router.put(`/roles/${selectedRole.id}`, formData, {
        onSuccess: (response: any) => {
          setRoles((prevRoles) =>
            prevRoles.map((role) => (role.id === selectedRole.id ? response.data : role))
          );
          handleCloseModal();
          setSuccessMessage("Role updated successfully!");
          setIsLoading(false);
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    } else if (modalType === "delete" && selectedRole) {
      router.delete(`/roles/${selectedRole.id}`, {
        onSuccess: () => {
          setRoles((prevRoles) => prevRoles.filter((role) => role.id !== selectedRole.id));
          handleCloseModal();
          setSuccessMessage("Role deleted successfully!");
          setIsLoading(false);
        },
        onError: (errors) => {
          setErrors(errors);
          setIsLoading(false);
        },
      });
    }
  };

  const togglePermission = (permissionId: number) => {
    setFormState((prev) => ({
      ...prev,
      permissions: prev.permissions.includes(permissionId)
        ? prev.permissions.filter((id) => id !== permissionId)
        : [...prev.permissions, permissionId],
    }));
  };

  return (
    <AuthenticatedLayout>
      <Head title="Roles Management" />
      <div className="p-6 bg-white rounded-lg shadow-md">
        {/* Success message toast */}
        {successMessage && (
          <div className="fixed top-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded shadow-md z-50 animate-fade-in-out">
            <div className="flex items-center">
              <svg className="h-5 w-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
              </svg>
              <p>{successMessage}</p>
            </div>
          </div>
        )}

        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-semibold">Roles Management</h1>
          <button
            onClick={() => handleOpenModal("create")}
            className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 flex items-center shadow-sm transition-colors duration-200"
          >
            <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Create Role
          </button>
        </div>

        {roles.length > 0 ? (
          <div className="overflow-x-auto rounded-lg border border-gray-200">
            <table className="w-full">
              <thead>
                <tr className="bg-gray-100">
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                  <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {roles.map((role, index) => (
                  role && (
                    <tr key={role.id} className="hover:bg-gray-50 transition-colors duration-150">
                      <td className="px-4 py-3 whitespace-nowrap">{index + 1}</td>
                      <td className="px-4 py-3 font-medium text-gray-900">{role.name || "N/A"}</td>
                      <td className="px-4 py-3">
                        <div className="flex flex-wrap gap-1 max-w-md">
                          {role.permissions?.map((permission) => (
                            permission && (
                              <span
                                key={permission.id}
                                className="inline-block bg-blue-100 text-blue-700 text-xs font-medium px-2 py-1 rounded-full"
                              >
                                {permission.name || "N/A"}
                              </span>
                            )
                          ))}
                        </div>
                      </td>
                      <td className="px-4 py-3 whitespace-nowrap">
                        <div className="flex space-x-2">
                          <button
                            onClick={() => handleOpenModal("edit", role)}
                            className="px-3 py-1 bg-amber-500 text-white rounded hover:bg-amber-600 transition-colors duration-200 flex items-center"
                          >
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                          </button>
                          <button
                            onClick={() => handleOpenModal("delete", role)}
                            className="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 transition-colors duration-200 flex items-center"
                          >
                            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Delete
                          </button>
                        </div>
                      </td>
                    </tr>
                  )
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <div className="bg-gray-50 rounded-lg p-8 text-center">
            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            <h3 className="mt-2 text-sm font-medium text-gray-900">No roles found</h3>
            <p className="mt-1 text-sm text-gray-500">Get started by creating a new role.</p>
            <div className="mt-6">
              <button
                onClick={() => handleOpenModal("create")}
                className="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <svg className="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                New Role
              </button>
            </div>
          </div>
        )}

        {/* Modal */}
        {isModalOpen && (
          <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white p-6 rounded-lg shadow-lg w-96 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
              <div className="flex justify-between items-center mb-4">
                <h2 className="text-xl font-semibold text-gray-800">
                  {modalType === "create" && "Create Role"}
                  {modalType === "edit" && "Edit Role"}
                  {modalType === "delete" && "Delete Role"}
                </h2>
                <button
                  onClick={handleCloseModal}
                  className="text-gray-500 hover:text-gray-700 focus:outline-none"
                >
                  <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>

              {modalType === "delete" ? (
                <>
                  <div className="p-4 mb-4 bg-red-50 border-l-4 border-red-400 text-red-700">
                    <div className="flex">
                      <svg className="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                      </svg>
                      <p>
                        Are you sure you want to delete the role <strong>{selectedRole?.name || "N/A"}</strong>?
                        This action cannot be undone.
                      </p>
                    </div>
                  </div>
                  <div className="flex justify-end space-x-2 mt-4">
                    <button
                      onClick={handleCloseModal}
                      className="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors duration-200"
                      disabled={isLoading}
                    >
                      Cancel
                    </button>
                    <button
                      onClick={handleSubmit}
                      className="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors duration-200 flex items-center"
                      disabled={isLoading}
                    >
                      {isLoading ? (
                        <>
                          <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          Deleting...
                        </>
                      ) : (
                        <>
                          <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                          Delete
                        </>
                      )}
                    </button>
                  </div>
                </>
              ) : (
                <form onSubmit={handleSubmit}>
                  <div className="mb-4">
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                      Role Name
                    </label>
                    <input
                      id="name"
                      type="text"
                      value={formState.name}
                      onChange={(e) => setFormState({ ...formState, name: e.target.value })}
                      required
                      className={`w-full border rounded px-3 py-2 mt-1 focus:ring-blue-500 focus:border-blue-500 ${
                        errors.name ? "border-red-500" : "border-gray-300"
                      }`}
                      placeholder="Enter role name"
                      disabled={isLoading}
                    />
                    {errors.name && (
                      <p className="text-red-500 text-xs mt-1">{errors.name}</p>
                    )}
                  </div>
                  <div className="mb-4">
                    <div className="flex justify-between items-center mb-1">
                      <label className="block text-sm font-medium text-gray-700">
                        Permissions 
                      </label>
                      {formState.permissions.length > 0 && 
                        <span className="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                          {formState.permissions.length} selected
                        </span>
                      }
                    </div>
                    <div className="flex space-x-2 mb-2">
                      <button 
                        type="button"
                        onClick={() => {
                          const allPermIds = allPermissions.map(p => p.id);
                          setFormState(prev => ({
                            ...prev,
                            permissions: allPermIds
                          }));
                        }}
                        className="px-3 py-1 bg-green-100 text-green-700 text-xs rounded-full hover:bg-green-200 transition-colors duration-200 flex items-center shadow-sm"
                        disabled={isLoading}
                      >
                        <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                        Select All
                      </button>
                      <button 
                        type="button"
                        onClick={() => {
                          setFormState(prev => ({
                            ...prev,
                            permissions: []
                          }));
                        }}
                        className="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-full hover:bg-red-200 transition-colors duration-200 flex items-center shadow-sm"
                        disabled={isLoading}
                      >
                        <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Clear All
                      </button>
                    </div>
                    <div className={`h-72 overflow-y-auto border rounded p-3 mt-2 ${
                      errors.permissions ? "border-red-500" : "border-gray-300"
                    }`}>
                      <div className="grid grid-cols-2 gap-2">
                        {allPermissions.map((permission) => (
                          permission && (
                            <div 
                              key={`perm-${permission.id}-${permissionKey}`} 
                              className={`flex items-center p-2 rounded ${formState.permissions.includes(permission.id) ? 'bg-blue-50' : 'hover:bg-gray-50'}`}
                            >
                              <input
                                type="checkbox"
                                id={`permission-${permission.id}`}
                                checked={formState.permissions.includes(permission.id)}
                                onChange={() => togglePermission(permission.id)}
                                className="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 rounded"
                                disabled={isLoading}
                              />
                              <label 
                                htmlFor={`permission-${permission.id}`} 
                                className={`text-sm cursor-pointer ${formState.permissions.includes(permission.id) ? 'font-medium text-blue-700' : 'text-gray-700'}`}
                              >
                                {permission.name || "N/A"}
                              </label>
                            </div>
                          )
                        ))}
                      </div>
                      {modalType === "edit" && formState.permissions.length === 0 && (
                        <div className="text-yellow-600 text-sm italic mt-2 text-center">
                          No permissions selected. Please select at least one permission.
                        </div>
                      )}
                    </div>
                    {errors.permissions && (
                      <p className="text-red-500 text-xs mt-1">{errors.permissions}</p>
                    )}
                  </div>
                  <div className="flex justify-end space-x-2 mt-4">
                    <button
                      type="button"
                      onClick={handleCloseModal}
                      className="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition-colors duration-200"
                      disabled={isLoading}
                    >
                      Cancel
                    </button>
                    <button
                      type="submit"
                      className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors duration-200 flex items-center"
                      disabled={isLoading}
                    >
                      {isLoading ? (
                        <>
                          <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                          </svg>
                          {modalType === "create" ? "Creating..." : "Updating..."}
                        </>
                      ) : (
                        <>
                          {modalType === "create" && (
                            <>
                              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                              </svg>
                              Create
                            </>
                          )}
                          {modalType === "edit" && (
                            <>
                              <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                              </svg>
                              Update
                            </>
                          )}
                        </>
                      )}
                    </button>
                  </div>
                </form>
              )}
            </div>
          </div>
        )}
      </div>
    </AuthenticatedLayout>
  );
};

export default RolesIndex;