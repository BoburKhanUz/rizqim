// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data13.dart';

part 'get_v1_admin_roles_response.g.dart';

@JsonSerializable()
class GetV1AdminRolesResponse {
  const GetV1AdminRolesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminRolesResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminRolesResponseFromJson(json);

  final bool success;
  final String message;
  final Data13 data;

  Map<String, Object?> toJson() => _$GetV1AdminRolesResponseToJson(this);
}
