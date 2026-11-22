// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher2.dart';
import 'entries12.dart';

part 'data359.g.dart';

@JsonSerializable()
class Data359 {
  const Data359({required this.voucher, required this.entries});

  factory Data359.fromJson(Map<String, Object?> json) =>
      _$Data359FromJson(json);

  final Voucher2 voucher;
  final List<Entries12> entries;

  Map<String, Object?> toJson() => _$Data359ToJson(this);
}
