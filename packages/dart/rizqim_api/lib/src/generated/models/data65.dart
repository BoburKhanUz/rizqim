// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'prices2.dart';

part 'data65.g.dart';

@JsonSerializable()
class Data65 {
  const Data65({required this.productId, required this.prices});

  factory Data65.fromJson(Map<String, Object?> json) => _$Data65FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final List<Prices2> prices;

  Map<String, Object?> toJson() => _$Data65ToJson(this);
}
