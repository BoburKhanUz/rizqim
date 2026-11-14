// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher2.dart';
import 'entries11.dart';

part 'data351.g.dart';

@JsonSerializable()
class Data351 {
  const Data351({required this.voucher, required this.entries});

  factory Data351.fromJson(Map<String, Object?> json) =>
      _$Data351FromJson(json);

  final Voucher2 voucher;
  final List<Entries11> entries;

  Map<String, Object?> toJson() => _$Data351ToJson(this);
}
