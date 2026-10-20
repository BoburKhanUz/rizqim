// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'prices3.dart';

part 'v1_owner_products_id_channel_prices_request_body.g.dart';

@JsonSerializable()
class V1OwnerProductsIdChannelPricesRequestBody {
  const V1OwnerProductsIdChannelPricesRequestBody({required this.prices});

  factory V1OwnerProductsIdChannelPricesRequestBody.fromJson(
    Map<String, Object?> json,
  ) => _$V1OwnerProductsIdChannelPricesRequestBodyFromJson(json);

  final List<Prices3> prices;

  Map<String, Object?> toJson() =>
      _$V1OwnerProductsIdChannelPricesRequestBodyToJson(this);
}
