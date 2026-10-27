// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data141.dart';

part 'post_v1_pos_bills_uuid_print_response.g.dart';

@JsonSerializable()
class PostV1PosBillsUuidPrintResponse {
  const PostV1PosBillsUuidPrintResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBillsUuidPrintResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosBillsUuidPrintResponseFromJson(json);

  final bool success;
  final String message;
  final Data141 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBillsUuidPrintResponseToJson(this);
}
