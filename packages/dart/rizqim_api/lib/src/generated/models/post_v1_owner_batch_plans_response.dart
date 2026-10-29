// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'post_v1_owner_batch_plans_response.g.dart';

@JsonSerializable()
class PostV1OwnerBatchPlansResponse {
  const PostV1OwnerBatchPlansResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1OwnerBatchPlansResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1OwnerBatchPlansResponseFromJson(json);

  final bool success;
  final String message;
  final List<dynamic> data;

  Map<String, Object?> toJson() => _$PostV1OwnerBatchPlansResponseToJson(this);
}
