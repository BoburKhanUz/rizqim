// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'period2.dart';
import 'summary2.dart';
import 'series.dart';
import 'by_channel2.dart';
import 'by_branch.dart';
import 'by_payment_method2.dart';
import 'by_order_type2.dart';

part 'data117.g.dart';

@JsonSerializable()
class Data117 {
  const Data117({
    required this.period,
    required this.branchId,
    required this.summary,
    required this.byStatus,
    required this.series,
    required this.byChannel,
    required this.byBranch,
    required this.byPaymentMethod,
    required this.byOrderType,
  });

  factory Data117.fromJson(Map<String, Object?> json) =>
      _$Data117FromJson(json);

  final Period2 period;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Summary2 summary;
  @JsonKey(name: 'by_status')
  final Map<String, int> byStatus;
  final List<Series> series;
  @JsonKey(name: 'by_channel')
  final List<ByChannel2> byChannel;
  @JsonKey(name: 'by_branch')
  final List<ByBranch> byBranch;
  @JsonKey(name: 'by_payment_method')
  final List<ByPaymentMethod2> byPaymentMethod;
  @JsonKey(name: 'by_order_type')
  final List<ByOrderType2> byOrderType;

  Map<String, Object?> toJson() => _$Data117ToJson(this);
}
