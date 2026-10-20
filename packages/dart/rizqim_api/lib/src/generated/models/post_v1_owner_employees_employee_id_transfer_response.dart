// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'user.dart';

part 'post_v1_owner_employees_employee_id_transfer_response.g.dart';

@JsonSerializable()
class PostV1OwnerEmployeesEmployeeIdTransferResponse {
  const PostV1OwnerEmployeesEmployeeIdTransferResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerEmployeesEmployeeIdTransferResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerEmployeesEmployeeIdTransferResponseFromJson(json);

  final bool success;
  final String message;
  final User? data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerEmployeesEmployeeIdTransferResponseToJson(this);
}
