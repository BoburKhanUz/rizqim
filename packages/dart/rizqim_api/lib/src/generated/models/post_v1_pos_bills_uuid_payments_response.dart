// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data119.dart';

part 'post_v1_pos_bills_uuid_payments_response.g.dart';

@JsonSerializable()
class PostV1PosBillsUuidPaymentsResponse {
  const PostV1PosBillsUuidPaymentsResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBillsUuidPaymentsResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBillsUuidPaymentsResponseFromJson(json);

  final bool success;
  final String message;
  final Data119 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBillsUuidPaymentsResponseToJson(this);
}
