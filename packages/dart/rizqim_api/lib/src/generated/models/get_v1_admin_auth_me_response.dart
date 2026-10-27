// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data10.dart';

part 'get_v1_admin_auth_me_response.g.dart';

@JsonSerializable()
class GetV1AdminAuthMeResponse {
  const GetV1AdminAuthMeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1AdminAuthMeResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1AdminAuthMeResponseFromJson(json);

  final bool success;
  final String message;
  final Data10 data;

  Map<String, Object?> toJson() => _$GetV1AdminAuthMeResponseToJson(this);
}
