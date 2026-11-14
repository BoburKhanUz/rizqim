// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data133.dart';

part 'post_v1_owner_pos_banquets_extras_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosBanquetsExtrasResponse {
  const PostV1OwnerPosBanquetsExtrasResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosBanquetsExtrasResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosBanquetsExtrasResponseFromJson(json);

  final bool success;
  final String message;
  final Data133 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosBanquetsExtrasResponseToJson(this);
}
