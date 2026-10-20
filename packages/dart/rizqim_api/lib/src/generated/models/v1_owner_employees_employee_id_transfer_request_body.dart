// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_owner_employees_employee_id_transfer_request_body.g.dart';

@JsonSerializable()
class V1OwnerEmployeesEmployeeIdTransferRequestBody {
  const V1OwnerEmployeesEmployeeIdTransferRequestBody({required this.branchId});

  factory V1OwnerEmployeesEmployeeIdTransferRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerEmployeesEmployeeIdTransferRequestBodyFromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;

  Map<String, Object?> toJson() =>
      _$V1OwnerEmployeesEmployeeIdTransferRequestBodyToJson(this);
}
