// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'account2.dart';
import 'entry.dart';

part 'data197.g.dart';

@JsonSerializable()
class Data197 {
  const Data197({required this.account, required this.entry});

  factory Data197.fromJson(Map<String, Object?> json) =>
      _$Data197FromJson(json);

  final Account2 account;
  final Entry entry;

  Map<String, Object?> toJson() => _$Data197ToJson(this);
}
