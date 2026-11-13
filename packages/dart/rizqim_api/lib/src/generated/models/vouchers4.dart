// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'sold4.dart';
import 'redeemed4.dart';

part 'vouchers4.g.dart';

@JsonSerializable()
class Vouchers4 {
  const Vouchers4({required this.sold, required this.redeemed});

  factory Vouchers4.fromJson(Map<String, Object?> json) =>
      _$Vouchers4FromJson(json);

  final Sold4 sold;
  final Redeemed4 redeemed;

  Map<String, Object?> toJson() => _$Vouchers4ToJson(this);
}
