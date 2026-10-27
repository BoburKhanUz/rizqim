// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data42.dart';

part 'get_v1_me_permissions_response.g.dart';

@JsonSerializable()
class GetV1MePermissionsResponse {
  const GetV1MePermissionsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1MePermissionsResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1MePermissionsResponseFromJson(json);

  final bool success;
  final String message;
  final Data42 data;

  Map<String, Object?> toJson() => _$GetV1MePermissionsResponseToJson(this);
}
