// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data235.dart';

part 'post_v1_pos_banquets_uuid_payments_row_pay_response.g.dart';

@JsonSerializable()
class PostV1PosBanquetsUuidPaymentsRowPayResponse {
  const PostV1PosBanquetsUuidPaymentsRowPayResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PostV1PosBanquetsUuidPaymentsRowPayResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PostV1PosBanquetsUuidPaymentsRowPayResponseFromJson(json);

  final bool success;
  final String message;
  final Data235 data;

  Map<String, Object?> toJson() =>
      _$PostV1PosBanquetsUuidPaymentsRowPayResponseToJson(this);
}
