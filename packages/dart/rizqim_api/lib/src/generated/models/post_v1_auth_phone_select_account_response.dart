// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data22.dart';

part 'post_v1_auth_phone_select_account_response.g.dart';

@JsonSerializable()
class PostV1AuthPhoneSelectAccountResponse {
  const PostV1AuthPhoneSelectAccountResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1AuthPhoneSelectAccountResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1AuthPhoneSelectAccountResponseFromJson(json);

  final bool success;
  final String message;
  final Data22 data;

  Map<String, Object?> toJson() =>
      _$PostV1AuthPhoneSelectAccountResponseToJson(this);
}
