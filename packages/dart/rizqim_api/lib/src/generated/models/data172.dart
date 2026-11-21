// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher.dart';
import 'entries.dart';

part 'data172.g.dart';

@JsonSerializable()
class Data172 {
  const Data172({required this.voucher, required this.entries});

  factory Data172.fromJson(Map<String, Object?> json) =>
      _$Data172FromJson(json);

  final Voucher voucher;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data172ToJson(this);
}
