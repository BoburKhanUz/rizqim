// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold6.dart';
import 'redeemed6.dart';

part 'vouchers6.g.dart';

@JsonSerializable()
class Vouchers6 {
  const Vouchers6({required this.sold, required this.redeemed});

  factory Vouchers6.fromJson(Map<String, Object?> json) =>
      _$Vouchers6FromJson(json);

  final Sold6 sold;
  final Redeemed6 redeemed;

  Map<String, Object?> toJson() => _$Vouchers6ToJson(this);
}
