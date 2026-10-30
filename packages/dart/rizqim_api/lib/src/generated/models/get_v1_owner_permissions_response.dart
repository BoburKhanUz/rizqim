// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data123.dart';

part 'get_v1_owner_permissions_response.g.dart';

@JsonSerializable()
class GetV1OwnerPermissionsResponse {
  const GetV1OwnerPermissionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerPermissionsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerPermissionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data123 data;

  Map<String, Object?> toJson() => _$GetV1OwnerPermissionsResponseToJson(this);
}
