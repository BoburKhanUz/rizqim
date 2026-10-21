// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data63.dart';

part 'put_v1_owner_permissions_response.g.dart';

@JsonSerializable()
class PutV1OwnerPermissionsResponse {
  const PutV1OwnerPermissionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerPermissionsResponse.fromJson(Map<String, Object?> json) =>
      _$PutV1OwnerPermissionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data63 data;

  Map<String, Object?> toJson() => _$PutV1OwnerPermissionsResponseToJson(this);
}
