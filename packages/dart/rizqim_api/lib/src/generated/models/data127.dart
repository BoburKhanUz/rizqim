// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'prices2.dart';

part 'data127.g.dart';

@JsonSerializable()
class Data127 {
  const Data127({required this.productId, required this.prices});

  factory Data127.fromJson(Map<String, Object?> json) =>
      _$Data127FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final List<Prices2> prices;

  Map<String, Object?> toJson() => _$Data127ToJson(this);
}
