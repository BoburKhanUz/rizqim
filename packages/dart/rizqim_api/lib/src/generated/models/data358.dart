// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher2.dart';
import 'entries12.dart';

part 'data358.g.dart';

@JsonSerializable()
class Data358 {
  const Data358({required this.voucher, required this.entries});

  factory Data358.fromJson(Map<String, Object?> json) =>
      _$Data358FromJson(json);

  final Voucher2 voucher;
  final List<Entries12> entries;

  Map<String, Object?> toJson() => _$Data358ToJson(this);
}
