// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data219.dart';

part 'post_v1_pos_banquets_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsResponse {
  const PostV1PosBanquetsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosBanquetsResponseFromJson(json);

  final bool success;
  final String message;
  final Data219 data;

  Map<String, Object?> toJson() => _$PostV1PosBanquetsResponseToJson(this);
}
