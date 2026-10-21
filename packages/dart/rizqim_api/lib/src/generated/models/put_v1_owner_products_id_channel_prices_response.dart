// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'data63.dart';

part 'put_v1_owner_products_id_channel_prices_response.g.dart';

@JsonSerializable()
class PutV1OwnerProductsIdChannelPricesResponse {
  const PutV1OwnerProductsIdChannelPricesResponse({
    required this.success,
    required this.message,
    required this.data,
  });

  factory PutV1OwnerProductsIdChannelPricesResponse.fromJson(
    Map<String, Object?> json,
  ) => _$PutV1OwnerProductsIdChannelPricesResponseFromJson(json);

  final bool success;
  final String message;
  final Data63 data;

  Map<String, Object?> toJson() =>
      _$PutV1OwnerProductsIdChannelPricesResponseToJson(this);
}
