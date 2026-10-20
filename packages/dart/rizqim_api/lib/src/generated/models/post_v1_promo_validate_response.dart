// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data55.dart';

part 'post_v1_promo_validate_response.g.dart';

@JsonSerializable()
class PostV1PromoValidateResponse {
  const PostV1PromoValidateResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PromoValidateResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PromoValidateResponseFromJson(json);

  final bool success;
  final String message;
  final Data55 data;

  Map<String, Object?> toJson() => _$PostV1PromoValidateResponseToJson(this);
}
