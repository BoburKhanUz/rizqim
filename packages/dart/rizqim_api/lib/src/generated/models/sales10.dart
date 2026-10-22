// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

part 'sales10.g.dart';

@JsonSerializable()
class Sales10 {
  const Sales10({required this.count, required this.amount});

  factory Sales10.fromJson(Map<String, Object?> json) =>
      _$Sales10FromJson(json);

  final int count;
  final String amount;

  Map<String, Object?> toJson() => _$Sales10ToJson(this);
}
