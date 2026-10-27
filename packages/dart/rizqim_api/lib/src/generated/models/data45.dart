// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period.dart';
import 'summary.dart';
import 'by_payment_method.dart';
import 'by_order_type.dart';
import 'by_channel.dart';
import 'top_products.dart';
import 'couriers.dart';
import 'times.dart';
import 'daily.dart';

part 'data45.g.dart';

@JsonSerializable()
class Data45 {
  const Data45({
    required this.period,
    required this.summary,
    required this.byStatus,
    required this.byPaymentMethod,
    required this.byOrderType,
    required this.byChannel,
    required this.topProducts,
    required this.couriers,
    required this.times,
    required this.daily,
    required this.generatedAt,
  });

  factory Data45.fromJson(Map<String, Object?> json) => _$Data45FromJson(json);

  final Period period;
  final Summary summary;
  @JsonKey(name: 'by_status')
  final Map<String, int> byStatus;
  @JsonKey(name: 'by_payment_method')
  final List<ByPaymentMethod> byPaymentMethod;
  @JsonKey(name: 'by_order_type')
  final List<ByOrderType> byOrderType;
  @JsonKey(name: 'by_channel')
  final List<ByChannel> byChannel;
  @JsonKey(name: 'top_products')
  final List<TopProducts> topProducts;
  final List<Couriers> couriers;
  final Times times;
  final List<Daily> daily;
  @JsonKey(name: 'generated_at')
  final String generatedAt;

  Map<String, Object?> toJson() => _$Data45ToJson(this);
}
