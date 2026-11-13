// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data299.dart';

part 'post_v1_pos_vouchers_sell_response.g.dart';

@JsonSerializable()
class PostV1PosVouchersSellResponse {
  const PostV1PosVouchersSellResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosVouchersSellResponse.fromJson(Map<String, Object?> json) =>
      _$PostV1PosVouchersSellResponseFromJson(json);

  final bool success;
  final String message;
  final Data299 data;

  Map<String, Object?> toJson() => _$PostV1PosVouchersSellResponseToJson(this);
}
