// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher2.dart';
import 'entries6.dart';

part 'data300.g.dart';

@JsonSerializable()
class Data300 {
  const Data300({required this.voucher, required this.entries});

  factory Data300.fromJson(Map<String, Object?> json) =>
      _$Data300FromJson(json);

  final Voucher2 voucher;
  final List<Entries6> entries;

  Map<String, Object?> toJson() => _$Data300ToJson(this);
}
