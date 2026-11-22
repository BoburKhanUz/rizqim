// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'totals2.dart';
import 'by_day.dart';
import 'by_staff2.dart';
import 'staff.dart';

part 'data165.g.dart';

@JsonSerializable()
class Data165 {
  const Data165({
    required this.branchId,
    required this.from,
    required this.to,
    required this.totals,
    required this.byDay,
    required this.byStaff,
    required this.undistributed,
    required this.staff,
  });

  factory Data165.fromJson(Map<String, Object?> json) =>
      _$Data165FromJson(json);

  @JsonKey(name: 'branch_id')
  final int branchId;
  final String from;
  final String to;
  final Totals2 totals;
  @JsonKey(name: 'by_day')
  final List<ByDay> byDay;
  @JsonKey(name: 'by_staff')
  final List<ByStaff2> byStaff;
  final String undistributed;
  final List<Staff> staff;

  Map<String, Object?> toJson() => _$Data165ToJson(this);
}
