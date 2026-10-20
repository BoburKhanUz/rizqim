// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'zone.dart';
import 'branch.dart';
import 'eta.dart';

part 'data8.g.dart';

@JsonSerializable()
class Data8 {
  const Data8({
    required this.zone,
    required this.branch,
    required this.deliveryPrice,
    required this.minOrderAmount,
    required this.freeDeliveryApplies,
    required this.meetsMinOrder,
    required this.eta,
  });

  factory Data8.fromJson(Map<String, Object?> json) => _$Data8FromJson(json);

  final Zone zone;
  final Branch branch;
  @JsonKey(name: 'delivery_price')
  final String deliveryPrice;
  @JsonKey(name: 'min_order_amount')
  final String minOrderAmount;
  @JsonKey(name: 'free_delivery_applies')
  final bool freeDeliveryApplies;
  @JsonKey(name: 'meets_min_order')
  final bool? meetsMinOrder;
  final Eta? eta;

  Map<String, Object?> toJson() => _$Data8ToJson(this);
}
