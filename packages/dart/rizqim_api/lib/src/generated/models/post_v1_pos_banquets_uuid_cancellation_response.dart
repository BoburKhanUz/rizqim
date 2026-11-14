// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data227.dart';

part 'post_v1_pos_banquets_uuid_cancellation_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsUuidCancellationResponse {
  const PostV1PosBanquetsUuidCancellationResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsUuidCancellationResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBanquetsUuidCancellationResponseFromJson(json);

  final bool success;
  final String message;
  final Data227 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBanquetsUuidCancellationResponseToJson(this);
}
