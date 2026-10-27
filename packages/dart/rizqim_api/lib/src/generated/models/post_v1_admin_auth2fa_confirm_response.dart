// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data3.dart';

part 'post_v1_admin_auth2fa_confirm_response.g.dart';

@JsonSerializable()
class PostV1AdminAuth2faConfirmResponse {
  const PostV1AdminAuth2faConfirmResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AdminAuth2faConfirmResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AdminAuth2faConfirmResponseFromJson(json);

  final bool success;
  final String message;
  final Data3 data;

  Map<String, Object?> toJson() =>
      _$PostV1AdminAuth2faConfirmResponseToJson(this);
}
