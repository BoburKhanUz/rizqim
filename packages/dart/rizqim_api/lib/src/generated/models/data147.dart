// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal8.dart';

part 'data147.g.dart';

@JsonSerializable()
class Data147 {
  const Data147({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data147.fromJson(Map<String, Object?> json) =>
      _$Data147FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal8 fiscal;

  Map<String, Object?> toJson() => _$Data147ToJson(this);
}
