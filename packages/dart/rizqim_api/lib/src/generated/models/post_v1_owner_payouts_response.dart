// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data107.dart';

part 'post_v1_owner_payouts_response.g.dart';

@JsonSerializable()
class PostV1OwnerPayoutsResponse {
  const PostV1OwnerPayoutsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerPayoutsResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerPayoutsResponseFromJson(json);

  final bool success;
  final String message;
  final Data107 data;

  Map<String, Object?> toJson() => _$PostV1OwnerPayoutsResponseToJson(this);
}
