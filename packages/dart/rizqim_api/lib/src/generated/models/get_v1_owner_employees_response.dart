// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data56.dart';

part 'get_v1_owner_employees_response.g.dart';

@JsonSerializable()
class GetV1OwnerEmployeesResponse {
  const GetV1OwnerEmployeesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerEmployeesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerEmployeesResponseFromJson(json);

  final bool success;
  final String message;
  final List<Data56> data;

  Map<String, Object?> toJson() => _$GetV1OwnerEmployeesResponseToJson(this);
}
