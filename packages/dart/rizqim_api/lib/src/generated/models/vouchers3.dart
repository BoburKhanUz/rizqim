// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold3.dart';
import 'redeemed3.dart';

part 'vouchers3.g.dart';

@JsonSerializable()
class Vouchers3 {
  const Vouchers3({required this.sold, required this.redeemed});

  factory Vouchers3.fromJson(Map<String, Object?> json) =>
      _$Vouchers3FromJson(json);

  final Sold3 sold;
  final Redeemed3 redeemed;

  Map<String, Object?> toJson() => _$Vouchers3ToJson(this);
}
