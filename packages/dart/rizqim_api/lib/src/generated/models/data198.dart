// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal9.dart';

part 'data198.g.dart';

@JsonSerializable()
class Data198 {
  const Data198({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data198.fromJson(Map<String, Object?> json) =>
      _$Data198FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal9 fiscal;

  Map<String, Object?> toJson() => _$Data198ToJson(this);
}
