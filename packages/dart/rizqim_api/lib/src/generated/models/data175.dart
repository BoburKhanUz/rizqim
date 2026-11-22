// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher.dart';
import 'entries.dart';

part 'data175.g.dart';

@JsonSerializable()
class Data175 {
  const Data175({required this.voucher, required this.entries});

  factory Data175.fromJson(Map<String, Object?> json) =>
      _$Data175FromJson(json);

  final Voucher voucher;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data175ToJson(this);
}
