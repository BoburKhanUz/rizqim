// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data31.dart';

part 'get_v1_owner_batch_plans_response.g.dart';

@JsonSerializable()
class GetV1OwnerBatchPlansResponse {
  const GetV1OwnerBatchPlansResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory GetV1OwnerBatchPlansResponse.fromJson(Map<String, Object?> json) =>
      _$GetV1OwnerBatchPlansResponseFromJson(json);

  final bool success;
  final String message;
  final Data31 data;

  Map<String, Object?> toJson() => _$GetV1OwnerBatchPlansResponseToJson(this);
}
