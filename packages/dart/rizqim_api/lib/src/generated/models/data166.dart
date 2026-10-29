// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account3.dart';
import 'entry2.dart';
import 'fiscal9.dart';

part 'data166.g.dart';

@JsonSerializable()
class Data166 {
  const Data166({
    required this.account,
    required this.entry,
    required this.fiscal,
  });

  factory Data166.fromJson(Map<String, Object?> json) =>
      _$Data166FromJson(json);

  final Account3 account;
  final Entry2 entry;
  final Fiscal9 fiscal;

  Map<String, Object?> toJson() => _$Data166ToJson(this);
}
