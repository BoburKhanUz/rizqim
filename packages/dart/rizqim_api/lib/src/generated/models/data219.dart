// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal10.dart';

part 'data219.g.dart';

@JsonSerializable()
class Data219 {
  const Data219({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data219.fromJson(Map<String, Object?> json) =>
      _$Data219FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal10 fiscal;

  Map<String, Object?> toJson() => _$Data219ToJson(this);
}
