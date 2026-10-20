// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'prices.dart';

part 'data43.g.dart';

@JsonSerializable()
class Data43 {
  const Data43({
    required this.productId,
    required this.channels,
    required this.prices,
  });

  factory Data43.fromJson(Map<String, Object?> json) => _$Data43FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final List<dynamic> channels;
  final List<Prices> prices;

  Map<String, Object?> toJson() => _$Data43ToJson(this);
}
