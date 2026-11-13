// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold2.dart';
import 'redeemed2.dart';

part 'vouchers2.g.dart';

@JsonSerializable()
class Vouchers2 {
  const Vouchers2({required this.sold, required this.redeemed});

  factory Vouchers2.fromJson(Map<String, Object?> json) =>
      _$Vouchers2FromJson(json);

  final Sold2 sold;
  final Redeemed2 redeemed;

  Map<String, Object?> toJson() => _$Vouchers2ToJson(this);
}
