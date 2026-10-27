// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data14.dart';

part 'put_v1_admin_roles_response.g.dart';

@JsonSerializable()
class PutV1AdminRolesResponse {
  const PutV1AdminRolesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1AdminRolesResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1AdminRolesResponseFromJson(json);

  final bool success;
  final String message;
  final Data14 data;

  Map<String, Object?> toJson() => _$PutV1AdminRolesResponseToJson(this);
}
