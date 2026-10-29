// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data118.dart';

part 'post_v1_owner_pos_tips_distributions_uuid_close_response.g.dart';

@JsonSerializable()
class PostV1OwnerPosTipsDistributionsUuidCloseResponse {
  const PostV1OwnerPosTipsDistributionsUuidCloseResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPosTipsDistributionsUuidCloseResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1OwnerPosTipsDistributionsUuidCloseResponseFromJson(json);

  final bool success;
  final String message;
  final Data118 data;

  Map<String, Object?> toJson() =>
      _$PostV1OwnerPosTipsDistributionsUuidCloseResponseToJson(this);
}
