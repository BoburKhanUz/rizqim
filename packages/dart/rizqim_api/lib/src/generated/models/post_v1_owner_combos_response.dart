// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data60.dart';

part 'post_v1_owner_combos_response.g.dart';

@JsonSerializable()
class PostV1OwnerCombosResponse {
  const PostV1OwnerCombosResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerCombosResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerCombosResponseFromJson(json);

  final bool success;
  final String message;
  final Data60 data;

  Map<String, Object?> toJson() => _$PostV1OwnerCombosResponseToJson(this);
}
