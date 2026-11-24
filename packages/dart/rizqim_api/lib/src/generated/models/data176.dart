// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'voucher.dart';
import 'entries.dart';

part 'data176.g.dart';

@JsonSerializable()
class Data176 {
  const Data176({required this.voucher, required this.entries});

  factory Data176.fromJson(Map<String, Object?> json) =>
      _$Data176FromJson(json);

  final Voucher voucher;
  final List<Entries> entries;

  Map<String, Object?> toJson() => _$Data176ToJson(this);
}
