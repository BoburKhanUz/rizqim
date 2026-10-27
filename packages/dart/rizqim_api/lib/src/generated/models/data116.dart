// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'prices2.dart';

part 'data116.g.dart';

@JsonSerializable()
class Data116 {
  const Data116({required this.productId, required this.prices});

  factory Data116.fromJson(Map<String, Object?> json) =>
      _$Data116FromJson(json);

  @JsonKey(name: 'product_id')
  final int productId;
  final List<Prices2> prices;

  Map<String, Object?> toJson() => _$Data116ToJson(this);
}
