// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold5.dart';
import 'redeemed5.dart';

part 'vouchers5.g.dart';

@JsonSerializable()
class Vouchers5 {
  const Vouchers5({required this.sold, required this.redeemed});

  factory Vouchers5.fromJson(Map<String, Object?> json) =>
      _$Vouchers5FromJson(json);

  final Sold5 sold;
  final Redeemed5 redeemed;

  Map<String, Object?> toJson() => _$Vouchers5ToJson(this);
}
