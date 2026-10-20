// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'v1_stores_store_id_rate_request_body.g.dart';

@JsonSerializable()
class V1StoresStoreIdRateRequestBody {
  const V1StoresStoreIdRateRequestBody({
    required this.rating,
    this.comment,
    this.orderId,
  });

  factory V1StoresStoreIdRateRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1StoresStoreIdRateRequestBodyFromJson(json);

  final int rating;
  final String? comment;
  @JsonKey(name: 'order_id')
  final int? orderId;

  Map<String, Object?> toJson() => _$V1StoresStoreIdRateRequestBodyToJson(this);
}
