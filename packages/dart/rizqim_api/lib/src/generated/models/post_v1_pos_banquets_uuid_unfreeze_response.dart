// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data243.dart';

part 'post_v1_pos_banquets_uuid_unfreeze_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsUuidUnfreezeResponse {
  const PostV1PosBanquetsUuidUnfreezeResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsUuidUnfreezeResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBanquetsUuidUnfreezeResponseFromJson(json);

  final bool success;
  final String message;
  final Data243 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBanquetsUuidUnfreezeResponseToJson(this);
}
