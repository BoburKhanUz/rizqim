// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'halls12.dart';
import 'untabled_orders.dart';

part 'data300.g.dart';

@JsonSerializable()
class Data300 {
  const Data300({
    required this.branchId,
    required this.generatedAt,
    required this.overdueAfterHours,
    required this.halls,
    required this.untabledOrders,
  });

  factory Data300.fromJson(Map<String, Object?> json) =>
      _$Data300FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  @JsonKey(name: 'generated_at')
  final String generatedAt;
  @JsonKey(name: 'overdue_after_hours')
  final int overdueAfterHours;
  final List<Halls12> halls;
  @JsonKey(name: 'untabled_orders')
  final List<UntabledOrders> untabledOrders;

  Map<String, Object?> toJson() => _$Data300ToJson(this);
}
