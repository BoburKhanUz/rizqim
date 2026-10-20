// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'type3.dart';

part 'v1_complaints_request_body.g.dart';

@JsonSerializable()
class V1ComplaintsRequestBody {
  const V1ComplaintsRequestBody({
    required this.orderId,
    required this.type,
    required this.description,
  });

  factory V1ComplaintsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1ComplaintsRequestBodyFromJson(json);

  @JsonKey(name: 'order_id')
  final int orderId;
  final Type3 type;
  final String description;

  Map<String, Object?> toJson() => _$V1ComplaintsRequestBodyToJson(this);
}
