// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold.dart';
import 'redeemed.dart';
import 'refunded.dart';
import 'outstanding.dart';
import 'blocked2.dart';
import 'expired.dart';

part 'data168.g.dart';

@JsonSerializable()
class Data168 {
  const Data168({
    required this.from,
    required this.to,
    required this.branchId,
    required this.sold,
    required this.redeemed,
    required this.refunded,
    required this.netChange,
    required this.outstanding,
    required this.blocked,
    required this.expired,
    required this.asOf,
  });

  factory Data168.fromJson(Map<String, Object?> json) =>
      _$Data168FromJson(json);

  final String from;
  final String to;
  @JsonKey(name: 'branch_id')
  final int? branchId;
  final Sold sold;
  final Redeemed redeemed;
  final Refunded refunded;
  @JsonKey(name: 'net_change')
  final String netChange;
  final Outstanding outstanding;
  final Blocked2 blocked;
  final Expired expired;
  @JsonKey(name: 'as_of')
  final String asOf;

  Map<String, Object?> toJson() => _$Data168ToJson(this);
}
